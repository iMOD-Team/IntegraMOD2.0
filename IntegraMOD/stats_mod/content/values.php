<?php
/***************************************************************************
 *                                values.php
 *                            -------------------
 *   begin                : Fri, Jan 03, 2003
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

class Content_values
{
	public $columns = -1;
	public $num_blocks = -1;
	public $num_columns = 0;
	public $value_order = '';
	public $column_data = array();
	public $width_step = array();
	public $align = 'center';

	function set_columns($data)
	{
		global $stats_template;

		if (($this->columns == -1) || ($this->num_blocks <= 0))
		{
			die('Invalid Call(' . get_class($content) . '): set_columns before set_view or num_blocks <= 0');
		}
		
		$i = 0;
		foreach ($data as $key => $value)
		{
			$this->column_data[$i]['key'] = $key;
			$this->column_data[$i]['value'] = $value;
			$i++;
		}

		$this->num_columns = $this->columns * $this->num_blocks;
		$stats_template->assign_vars(array(
			'NUM_COLUMNS' => $this->num_columns)
		);

		$width = round(100 / $this->num_columns);
		$this->width_step = array();
		
		if ($width * $this->num_columns > 100)
		{
			$step = ($width * $this->num_columns) - 100;
			for ($i = 0; $i < $this->num_columns; $i+=$step)
			{
				$this->width_step[] = $width-1;
				for ($j = 0; $j < $step-1; $j++)
				{
					$this->width_step[] = $width;
				}
			}
		}
		else
		{
			for ($i = 0; $i < $this->num_columns; $i++)
			{
				$this->width_step[] = $width;
			}
		}

		$c = 0;
		for ($i = 1; $i <= $this->num_blocks; $i++)
		{
			for ($j = 1; $j <= $this->columns; $j++)
			{
				$first_column = ($i*$j == 1) ? TRUE : FALSE;
				$last_column = ($i*$j == $this->num_columns) ? TRUE : FALSE;

				$stats_template->assign_block_vars('column', array(
					'FIRST_COLUMN' => $first_column,
					'LAST_COLUMN' => $last_column,
					'WIDTH' => $this->width_step[$c],
					'VALUE' => $this->column_data[$j-1]['value'])
				);

				$c++;
			}
		}
	}

	function value_array($data)
	{
		$data_return = array();
		
		for ($i = 0; $i < ((($__imc0 = $data) instanceof Countable || is_array($__imc0)) ? count($__imc0) : 0); $i++)
		{
			for ($j = 0; $j < ((($__imc1 = $data[$i]) instanceof Countable || is_array($__imc1)) ? count($__imc1) : 0); $j++)
			{
				$data_return[$j][$i] = $data[$i][$j];
			}
		}

		return ($data_return);
	}

	function iterate_values()
	{
		global $core, $stats_template, $phpbb_root_path, $phpEx, $stat_functions, $lang;

		// make global...
		if (((($__imc2 = $core->global_array) instanceof Countable || is_array($__imc2)) ? count($__imc2) : 0) > 0)
		{
			eval('global ' . implode(', ', $core->global_array) . ';');
		}

		if ($this->value_order == 'up_down')
		{
			$one_block = ((($__imc3 = $core->calculation_data) instanceof Countable || is_array($__imc3)) ? count($__imc3) : 0) / $this->num_blocks;
			$one_block = intval($one_block);
			$one_block += ((($__imc4 = $core->calculation_data) instanceof Countable || is_array($__imc4)) ? count($__imc4) : 0) % $this->num_blocks;
		}
		else if ($this->value_order == 'left_right')
		{
			$one_block = $this->num_blocks;
		}

		$iteration = 0;

		for ($i = 0; $i < ((($__imc5 = $core->calculation_data) instanceof Countable || is_array($__imc5)) ? count($__imc5) : 0); $i = $i + $this->num_blocks)
		{
			$w_iteration = 0;
			
			$stats_template->assign_block_vars('row', array(
				'ROW' => $i)
			);

			$row_value = array();

			$num = 0;

			for ($j = 0; $j < $this->num_blocks; $j++)
			{
				for ($k = 0; $k < $this->columns; $k++)
				{
					if ($this->value_order == 'up_down')
					{
						$row_value[$num] = $core->calculation_data[$iteration+($one_block*$j)][$k];
					}
					else if ($this->value_order == 'left_right')
					{
						$row_value[$num] = $core->calculation_data[$j+($iteration*$one_block)][$k];
					}
					$num++;
				}
			}

			for ($j = 0; $j < $this->num_columns; $j++)
			{
				$first_column = ($j == 0) ? TRUE : FALSE;
				$last_column = ($j == $this->num_columns-1) ? TRUE : FALSE;

				$stats_template->assign_block_vars('row.row_column', array(
					'FIRST_COLUMN' => $first_column,
					'LAST_COLUMN' => $last_column,
					'ROW' => $j,
					'WIDTH' => $this->width_step[$w_iteration],
					'ALIGN' => $this->align,
					'VALUE' => $row_value[$j])
				);
				
				$w_iteration++;
			}

			$iteration++;	
		}
	}

}

?>
