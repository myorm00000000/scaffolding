<?php

class modelCliController extends O_CliController {

	public function createCliAction($prefix='') {
		mkdir($_ENV['HOME'].'/Desktop/models');

		$n = chr(10);
		$t = chr(9);
		$column_types = [];

		$tables = $this->db->list_tables();

		foreach ($tables as $table) {
			$fields = $this->db->field_data($table);

			echo $table.chr(10);

			$txt = '<?php'.$n.$n;

			$model_name = ucfirst($table).'_model';

			$primary_key = '';

			foreach ($fields as $field) {
				if ($field->primary_key == 1) {
					$primary_key = $field->name;
					
					break;
				}
			}

			$txt .= 'class '.$prefix.$model_name.' extends Database_model {'.$n;
			$txt .= $t.'protected $table =\''.$table.'\';'.$n;
			$txt .= $t.'public $primary_key =\''.$primary_key.'\';'.$n.$n;
			$txt .= $t.'protected $rules = ['.$n;

			$all_fields = [];

			foreach ($fields as $field) {
				$rules = $this->get_rules($field);

				$txt .= $t.$t."'".$field->name."' => ['field' => '".$field->name."', 'label' => '".ucwords(str_replace('_',' ',$field->name))."', 'rules' => '".$rules."'],".$n;
				$all_fields[] = $field->name;
				$column_types[$field->type] = $field->type;
			}

			$txt .= $t.'];'.$n;

			$txt .= $t.'protected $rule_sets = ['.$n;

			$fields_combo = implode(',',$all_fields);

			$txt .= $t.$t."'insert' => '".$fields_combo."',".$n;
			$txt .= $t.$t."'update' => '".str_replace($primary_key.',','',$fields_combo)."',".$n;
			$txt .= $t.'];'.$n;

			$txt .= '} /* end class */'.$n;

			$filename = $_ENV['HOME'].'/Desktop/models/'.$prefix.strtolower($model_name).'.php';

			file_put_contents($filename,$txt);
		}

		print_r($column_types);
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
			'int' => 'if_empty[0]|integer|max_length[10]|less_than[4294967295]|filter_int[10]',
			'tinyint' => 'if_empty[0]|integer|max_length[10]|less_than[255]|filter_int[10]',
			'smallint' => 'if_empty[0]|integer|max_length[10]|less_than[4294967295]|filter_int[10]',
			'bigint' => 'if_empty[0]|integer|max_length[20]|less_than[4294967295]|filter_int[10]',
			'double' => 'if_empty[0]|integer|max_length[20]|less_than[4294967295]|filter_int[10]',
			'varchar' => 'max_length[{max_length}]|filter_input[{max_length}]',
			'text' => 'max_length[16384]|filter_textarea[16384]',
			'longtext' => 'max_length[16384]|filter_textarea[16384]',
			'datetime' => 'if_empty[now(Y-m-d H:i:s)]|required|max_length[24]|valid_datetime|filter_input[24]',
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