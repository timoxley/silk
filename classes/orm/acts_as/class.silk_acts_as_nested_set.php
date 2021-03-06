<?php // -*- mode:php; tab-width:4; indent-tabs-mode:t; c-basic-offset:4; -*-
// The MIT License
// 
// Copyright (c) 2008 Ted Kulp
// 
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
// 
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
// 
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.
/**
 * Class to easily add nested set functionality to your ORM class.
 * In order to use this, do the following:
 * 1. Add interger fields named lft, rgt, parent_id and item_order to your table.
 * 2. Initialize lft, rgt, parent_id and item_order in your orm class.  
 *    ex. var $params = array('lft' => 1, 'rgt' => 1, 'parent_id => -1, 'item_order' => 0);
 * 3. Optionally, create a "dummy" root item with lft = 1, rgt = 2, parent_id = -1 and item_oder = 1,
 *    depending on how you want to treat the root of the tree.
 *
 * @since 1.0
 * @author Ted Kulp
 **/
class SilkActsAsNestedSet extends SilkActsAs
{
	function __construct()
	{
		parent::__construct();
	}
	
	function after_save(&$obj, &$result)
	{
		$result = $obj->complete_transaction();
	}
	
	function before_save(&$obj)
	{
		//$this->prop_names = implode(',', $this->get_loaded_property_names());
		$db = db();
		$table_name = $obj->get_table();
		$obj->begin_transaction();

		if ($obj->id == -1)
		{			
			$this->make_space_for_child($obj);
		}
		else
		{
			$old_content = $obj->find_by_id($obj->id);
			if ($old_content)
			{
				if ($obj->parent_id != $old_content->parent_id)
				{
					//TODO: Make this whole bit a transaction (after_save included)

					//First reorder
					$query = "UPDATE {$table_name} SET item_order = item_order - 1 WHERE parent_id = ? AND item_order > ?";
					$result = $db->Execute($query, array($old_content->parent_id, $old_content->item_order));
					
					//Flip all children over to the negative side so they don't get in the way
					$query = "UPDATE {$table_name} SET lft = (lft * -1), rgt = (rgt * -1) WHERE lft > ? AND rgt < ?";
					$result = $db->Execute($query, array($old_content->lft, $old_content->rgt));
					
					//Then move lft and rgt so that this node doesn't "exist"
					$diff = $old_content->rgt - $old_content->lft + 1;

					$query = "UPDATE {$table_name} SET lft = (lft - ?) WHERE lft > ?";
					$result = $db->Execute($query, array($diff, $old_content->lft));
					
					$query = "UPDATE {$table_name} SET rgt = (rgt - ?) WHERE rgt > ?";
					$result = $db->Execute($query, array($diff, $old_content->rgt));
					
					//Now make a new hole under the new child
					$this->make_space_for_child($obj);
					
					//Update the ones currently in the negative space the distance that we've moved
					$moved_by_how_much = $old_content->lft - $obj->lft;
					$query = "UPDATE {$table_name} SET lft = (lft + ?), rgt = (rgt + ?) WHERE lft < 0 AND rgt < 0";
					$result = $db->Execute($query, array($moved_by_how_much, $moved_by_how_much));
					
					//And flip those back over to the positive side...  hopefully in the correct place now
					$query = "UPDATE {$table_name} SET lft = (lft * -1), rgt = (rgt * -1) WHERE lft < 0 AND rgt < 0";
					$result = $db->Execute($query);
				}
			}
		}
	}
	
	function make_space_for_child(&$obj)
	{
		$db = db();
		$table_name = $obj->get_table();

		$diff = $obj->rgt - $obj->lft;
		if ($diff < 2)
		{
			$diff = 1;
		}
		$right = $db->GetOne("SELECT max(rgt) FROM {$table_name}");
		
		if ($obj->parent_id > -1)
		{
			$row = $db->GetRow("SELECT rgt FROM {$table_name} WHERE id = ?", array($obj->parent_id));
			if ($row)
			{
				$right = $row['rgt'];
			}

			$db->Execute("UPDATE {$table_name} SET lft = lft + ? WHERE lft > ?", array($diff + 1, $right));
			$db->Execute("UPDATE {$table_name} SET rgt = rgt + ? WHERE rgt >= ?", array($diff + 1, $right));
		}
		else
		{
			$right = $right + $diff;
		}
		
		$obj->lft = $right;
		$obj->rgt = $right + $diff;
		
		$query = "SELECT max(item_order) as new_order FROM {$table_name} WHERE parent_id = ?";
		$row = &$db->GetRow($query, array($obj->parent_id));
		if ($row)
		{
			if ($row['new_order'] < 1)
			{
				$obj->item_order = 1;
			}
			else
			{
				$obj->item_order = $row['new_order'] + 1;
			}
		}
	}
	
	function after_delete(&$obj)
	{
		$table_name = $obj->get_table();

		#Fix the item_order if necessary
		$query = "UPDATE {$table_name} SET item_order = item_order - 1 WHERE parent_id = ? AND item_order > ?";
		$result = db()->Execute($query, array($obj->parent_id, $obj->item_order));
		
		#And fix the lft, rgt on items after this one
		$query = "UPDATE {$table_name} SET lft = lft - 2 WHERE lft > ?";
		$result = db()->Execute($query, array($obj->lft));
		
		$query = "UPDATE {$table_name} SET rgt = rgt - 2 WHERE rgt > ?";
		$result = db()->Execute($query, array($obj->rgt));
	}
	
	function move_up(&$obj)
	{
		$this->shift_position($obj, 'up');
	}
	
	function move_down(&$obj)
	{
		$this->shift_position($obj, 'down');
	}
	
	function shift_position(&$obj, $direction = 'up')
	{
		$new_item_order = $obj->item_order;
		if ($direction == 'up')
			$new_item_order--;
		else
			$new_item_order++;

		$other_content = $obj->find_by_parent_id_and_item_order($obj->parent_id, $new_item_order);
		
		if ($other_content != null)
		{
			$db = db();
			$table_name = $obj->get_table();
			
			$old_lft = $other_content->lft;
			$old_rgt = $other_content->rgt;
			
			//Assume down
			$diff = $obj->lft - $old_lft;
			$diff2 = $obj->rgt - $old_rgt;

			if ($direction == 'up')
			{
				//Now up
				$diff = $obj->rgt - $old_rgt;
				$diff2 = $obj->lft - $old_lft;
			}
			
			$time = $db->DBTimeStamp(time());
			
			//Flip me and children into the negative space
			$query = "UPDATE {$table_name} SET lft = (lft * -1), rgt = (rgt * -1), modified_date = {$time} WHERE lft >= ? AND rgt <= ?";
			$db->Execute($query, array($obj->lft, $obj->rgt));
			
			//Shift the other content to the new position
			$query = "UPDATE {$table_name} SET lft = (lft + ?), rgt = (rgt + ?), modified_date = {$time} WHERE lft >= ? AND rgt <= ?";
			$db->Execute($query, array($diff, $diff, $old_lft, $old_rgt));
			
			//Shift me to the new position in the negative space
			$query = "UPDATE {$table_name} SET lft = (lft + ?), rgt = (rgt + ?), modified_date = {$time} WHERE lft < 0 AND rgt < 0";
			$db->Execute($query, array($diff2, $diff2));
			
			//Flip me back over to the positive side...  hopefully in the correct place now
			$query = "UPDATE {$table_name} SET lft = (lft * -1), rgt = (rgt * -1), modified_date = {$time} WHERE lft < 0 AND rgt < 0";
			$result = $db->Execute($query);
			
			//Now flip the item orders
			$query = "UPDATE {$table_name} SET item_order = ?, modified_date = {$time} WHERE id = ?";
			$db->Execute($query, array($other_content->item_order, $obj->id));
			$db->Execute($query, array($obj->item_order, $other_content->id));
			
			$obj->lft = $obj->lft - $diff2;
			$obj->rgt = $obj->rgt - $diff2;
			
			$obj->item_order = $other_content->item_order;
		}
	}
}

# vim:ts=4 sw=4 noet
?>