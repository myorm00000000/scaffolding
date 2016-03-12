<?php

class createController extends O_CliController {

	public function packageCliAction($path=null) {
		$this->load->helper('inflector');

		if (!$path) {
			show_error('Please provide path');
		}

		$path = implode('/',func_get_args());

		$path = '/'.strtolower(trim($path,'/'));
		$dir = dirname($path);
		$filename = basename($path);

		$root = ROOTPATH.'/packages/'.$filename;

		mkdir($root);
		touch($root.'/composer.json');

		mkdir($root.'/public');
		mkdir($root.'/support');
		mkdir($root.'/support/migrations');
		touch($root.'/support/onload.php');
		touch($root.'/support/migrations/v1.0.0-'.$filename.'.php');

		mkdir($root.'/controllers');
		mkdir($root.'/controllers/'.$dir,0777,true);
		touch($root.'/controllers/'.$dir.'/'.ucfirst($filename).'Controller.php');

		mkdir($root.'/helpers');
		mkdir($root.'/language');
		mkdir($root.'/libraries');

		mkdir($root.'/models');
		touch($root.'/models/'.ucfirst($filename).'_model.php');

		mkdir($root.'/views');
		mkdir($root.'/views/'.$path,0777,true);
		touch($root.'/views/'.$path.'/index.php');
		touch($root.'/views/'.$path.'/form.php');

		$data = [];

		$this->model($filename,$data);
		$this->view($filename,$data);
		
		$data['controller'] = ucfirst(strtolower(str_replace('-','_',$filename)));
		$data['uname'] = ucfirst(strtolower($filename));
		$data['lname'] = strtolower($filename);
		$data['path'] = trim($path,'/');
		$data['model'] = $filename.'_model';
		$data['single'] = singular($filename);
		$data['plural'] = plural($filename);
		$data['name'] = strtolower($filename);

		//var_dump($data);

		file_put_contents($root.'/views/'.$path.'/index.php',$this->merge('index',$data));
		file_put_contents($root.'/views/'.$path.'/form.php',$this->merge('form',$data));
		file_put_contents($root.'/controllers/'.$dir.'/'.ucfirst($filename).'Controller.php',$this->merge('controller',$data));
		file_put_contents($root.'/models/'.ucfirst($filename).'_model.php',$this->merge('model',$data));
		file_put_contents($root.'/support/migrations/v1.0.0-'.$filename.'.php',$this->merge('migration',$data));
		file_put_contents($root.'/composer.json',$this->merge('composer',$data));
	}

	protected function merge($file,$data) {
		$parser = new Lex\Parser();

		$template = $parser->parse(file_get_contents(__DIR__.'/../../views/'.$file.'.tmpl'), $data);

		return str_replace('&lt;?php','<?php',$template);
	}

	protected function model($tablename,&$data) {

		if (in_array($tablename,$this->db->list_tables())) {
			$data['has_table'] = true;
			$data['tablename'] = $tablename;
			$data['table_create_sql'] = $this->show_create_table($tablename);

			$fields = $this->db->field_data($tablename);

			$primary_key = '';

			foreach ($fields as $field) {
				$data['model_fields_rules'][] = ['name'=>$field->name,'human_name'=>ucwords(str_replace('_',' ',$field->name)),'rule'=>$this->get_rules($field)];
				$all_fields[] = $field->name;

				if ($field->primary_key == 1) {
					$data['primary_id'] = $field->name;
					$primary_key = $field->name;
				}
			}

			$fields_combo = implode(',',$all_fields);

			$data['model_insert_rule'] = str_replace($primary_key.',','',$fields_combo);
			$data['model_update_rule'] = $fields_combo;
		}
	}

	protected function show_create_table($tablename) {
		$dbc = $this->db->query('SHOW CREATE TABLE '.$tablename);
		
		$dbr = $dbc->result();
		
		$dbr = (array)$dbr[0];
		
		return $dbr['Create Table'];
	}

	protected function view($tablename,&$data) {
		$list_columns = 4;

		if (in_array($tablename,$this->db->list_tables())) {
			$fields = $this->db->field_data($tablename);

			$primary_key = '';

			foreach ($fields as $field) {
				if ($field->primary_key == 1) {
					$data['primary_id'] = $field->name;
				} elseif ($list_col < $list_columns) {
					$list_col++;
					$data['i_column'.$list_col] = $field->name;
					$data['i_column_name'.$list_col] = $field->name;

					$data['form_fields'][] = ['fieldtext'=>ucwords(str_replace('_',' ',$field->name)),'fieldname'=>$field->name];
				} else {
					$data['form_fields'][] = ['fieldtext'=>ucwords(str_replace('_',' ',$field->name)),'fieldname'=>$field->name];
				}
			}

		}
	}

	public function get_rules($field) {
		/*
		[name] => id
		[type] => int
		[max_length] => 11
		[default] =>
		[primary_key] => 1

		[int] => int
		[varchar] => varchar
		[tinyint] => tinyint
		[smallint] => smallint
		[double] => double
		[text] => text
		[datetime] => datetime
		[longtext] => longtext
		[bigint] => bigint
		*/
		$types = [
			'int' 			=> 'if_empty[0]|integer|max_length[10]|less_than[4294967295]|filter_int[10]',
			'smallint' 	=> 'if_empty[0]|integer|max_length[10]|less_than[4294967295]|filter_int[10]',
			'bigint' 		=> 'if_empty[0]|integer|max_length[20]|less_than[4294967295]|filter_int[10]',
			'double' 		=> 'if_empty[0]|integer|max_length[20]|less_than[4294967295]|filter_int[10]',
			'tinyint' 	=> 'if_empty[0]|integer|max_length[10]|less_than[255]|filter_int[10]',
			'varchar' 	=> 'max_length[{max_length}]|filter_input[{max_length}]',
			'text' 			=> 'max_length[16384]|filter_textarea[16384]',
			'longtext' 	=> 'max_length[16384]|filter_textarea[16384]',
			'datetime' 	=> 'max_length[24]|valid_datetime|filter_input[24]',
		];

		$rule = $types[$field->type];

		$rule = str_replace('{max_length}',$field->max_length,$rule);

		if (!empty($field->default)) {
			$rule = str_replace('if_empty[0]|','',$rule);
			$rule = 'if_empty['.$field->default.']|'.$rule;
		}

		if ($field->primary_key == 1) {
			$rule = 'required|'.$rule;
		}

		return $rule;
	}

} /* end class */