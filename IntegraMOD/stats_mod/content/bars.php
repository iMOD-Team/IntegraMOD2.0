<?php
/***************************************************************************
 *                                bars.php
 *                            -------------------
 *   begin                : Wed, Jan 01, 2003
 *   copyright            : (C) 2003 Meik Sievertsen
 *   email                : acyd.burn@gmx.de
 *
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

class Content_bars
{
	public $columns = 0; // Number of columns
	public $rows = 0; // Number of maximum rows
	public $column_data = array();
	public $align = array();
	public $percentage_sign = FALSE; // Display % after percentage

	// init_bar Variables
	public $bar_loaded = FALSE;
	public $loaded_bar_images = array();
	public $current_template_path = '';

	// math values
	public $math_calculation_key = '';
	public $math_min_value = 0;
	public $math_max_value = 0;
	public $percentage = 0;
	public $bar_percent = 0;
	

	function init_bars($bars = '')
	{
		global $board_config, $userdata, $theme, $db, $stats_template, $phpbb_root_path, $template;
		
		if (is_array($bars))
		{
			$this->loaded_bar_images['left'] = $bars['left'];
			$this->loaded_bar_images['right'] = $bars['right'];
			$this->loaded_bar_images['bar'] = $bars['bar'];
		}

		if ($this->bar_loaded)
		{
			$stats_template->assign_vars(array(
				'LEFT_GRAPH_IMAGE' => $phpbb_root_path . $this->current_template_path . $this->loaded_bar_images['left'],
				'RIGHT_GRAPH_IMAGE' => $phpbb_root_path . $this->current_template_path . $this->loaded_bar_images['right'],
				'GRAPH_IMAGE' => $phpbb_root_path . $this->current_template_path . $this->loaded_bar_images['bar'])
			);

			return;
		}

		//
		// Getting voting bar info
		//
		
		$this->current_template_path = $template->root . '/';
		
		$stats_template->assign_vars(array(
			'LEFT_GRAPH_IMAGE' => $phpbb_root_path . $this->current_template_path . $this->loaded_bar_images['left'],
			'RIGHT_GRAPH_IMAGE' => $phpbb_root_path . $this->current_template_path . $this->loaded_bar_images['right'],
			'GRAPH_IMAGE' => $phpbb_root_path . $this->current_template_path . $this->loaded_bar_images['bar'])
		);

		$this->bar_loaded = TRUE;
	}

	function init_math($key, $min, $max)
	{
		$this->math_calculation_key = $key; // 'user_posts'
		$this->math_min_value = intval($min);
		$this->math_max_value = intval($max);
	}

	function set_columns($data)
	{
		global $stats_template;

		$i = 0;
		foreach ($data as $key => $value)
		{
			// check pre-defined value
			if (is_array($value))
			{
				foreach ($value as $k => $v)
				{
					$this->column_data[$i]['key'] = $k;
				 	$this->column_data[$i]['value'] = $v;
					break;
				}
			}
			else
			{
				$this->column_data[$i]['key'] = $key;
				$this->column_data[$i]['value'] = $value;
			}
			$i++;
		}

		$stats_template->assign_vars(array(
			'NUM_COLUMNS' => $this->columns)
		);

		// to the run_module part ?
		for ($i = 0; $i < $this->columns; $i++)
		{
			$first_column = ($i == 0) ? TRUE : FALSE;
			$last_column = ($i == $this->columns-1) ? TRUE : FALSE;

			$stats_template->assign_block_vars('column', array(
				'FIRST_COLUMN' => $first_column,
				'LAST_COLUMN' => $last_column,
				'VALUE' => $this->column_data[$i]['value'])
			);
		}
	}

	function align_rows($data)
	{
		// Default: center
		$this->align = $data;
	}

	function do_math($value)
	{
		$cst = ($this->math_min_value > 0) ? 90 / $this->math_min_value : 90;

		if ($value != 0)
		{
			$this->percentage = ( $this->math_max_value ) ? round( min(100, ($value / $this->math_max_value) * 100)) : 0;
		}
		else
		{
			$this->percentage = 0;
		}

		$this->bar_percent = round($value * $cst);
	}

	function set_rows($data, $auth_data)
	{
		global $core, $stats_template, $phpbb_root_path, $phpEx, $stat_functions, $lang;

		// make global...
		if (((($__imc0 = $core->global_array) instanceof Countable || is_array($__imc0)) ? count($__imc0) : 0) > 0)
		{
			eval('global ' . implode(', ', $core->global_array) . ';');
		}

		// If we are in an auth condition, please clean them first
		$auth_array = array();
		$authed = FALSE;
		if ($auth_data)
		{
			$auth_array = $stat_functions->clean_auth_values($auth_data);
			$authed = TRUE;
		}

		for ($i = 0; $i < ((($__imc1 = $core->calculation_data) instanceof Countable || is_array($__imc1)) ? count($__imc1) : 0); $i++)
		{
			$rank_column = array();
			$graph_column = array();

			$core->calc_index = $i;
			$this->do_math(intval($core->data($this->math_calculation_key)));

			$stats_template->assign_block_vars('row', array());
			$row_value = array();
			$auth_replace = array();

			for ($j = 0; $j < $this->columns; $j++)
			{
				eval('$result = ' . $data[$j] . ';');

				$rank_column[$j] = FALSE;
				$graph_column[$j] = FALSE;

				if (empty($this->align[$j]))
				{
					$this->align[$j] = 'center';
				}

				$auth_replace[$j]['replace'] = false;
				$auth_replace[$j]['lang'] = false;
				if ($result == '__PRE_DEFINED_VALUE__')
				{
					if ($this->column_data[$j]['key'] == '__PRE_DEFINE_RANK__')
					{
						$row_value[$j] = $i+1;
						$rank_column[$j] = TRUE;
					}
					else if ($this->column_data[$j]['key'] == '__PRE_DEFINE_PERCENT__')
					{
						$row_value[$j] = $this->percentage;
						if ($this->percentage_sign)
						{
							$row_value[$j] .= '%';
						}
					}
					else if ($this->column_data[$j]['key'] == '__PRE_DEFINE_GRAPH__')
					{
						$row_value[$j] = $this->bar_percent;
						$graph_column[$j] = TRUE;
					}
				}
				else
				{
					if (!$authed)
					{
						$row_value[$j] = $result;
					}
					else
					{
						eval('$auth_key = ' . $auth_array['auth_key'] . ';');
						if ($auth_array['auth_check'][$auth_key])
						{
							$row_value[$j] = $result;
						}
						else
						{
							eval('$result = ' . $auth_array['auth_replacement'][$j] . ';');
							$auth_replace[$j]['replace'] = TRUE;
							if ( (is_string($auth_array['auth_replacement'][$j])) && (strstr($auth_array['auth_replacement'][$j], '$lang')) )
							{
								$auth_replace[$j]['lang'] = TRUE;
							}

							$row_value[$j] = $result;
						}
					}
				}
			}

			for ($j = 0; $j < $this->columns; $j++)
			{
				$first_column = ($j == 0) ? TRUE : FALSE;
				$last_column = ($j == $this->columns-1) ? TRUE : FALSE;

				$stats_template->assign_block_vars('row.row_column', array(
					'FIRST_COLUMN' => $first_column,
					'LAST_COLUMN' => $last_column,
					'RANK_COLUMN' => $rank_column[$j],
					'GRAPH_COLUMN' => $graph_column[$j],
					'ROW' => $i,
					'VALUE' => $row_value[$j],
					'ALIGNMENT' => $this->align[$j],
					'AUTH_REPLACEMENT' => $auth_replace[$j]['replace'],
					'AUTH_LANG_ENTRY' => $auth_replace[$j]['lang'])
				);
			}

			$core->calc_index++;
		}
	}
}

?>
