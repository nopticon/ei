<?php
/*
<NPT, a web development framework.>
Copyright (C) <2009>  <NPT>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
if (!defined('IN_EX')) exit;

require_once('db.dcom.php');

class database extends dcom {
	public function __construct($d = false, $r = false)
	{
		$this->access($d);
		
		ob_start();
		$this->connect = @mysql_connect($this->_access['server'], $this->_access['login'], $this->_access['secret'], false, MYSQL_CLIENT_COMPRESS);
		ob_end_clean();
		
		if (!$this->connect) {
			if ($r !== false) {
				return false;
			}
			
			exit('330');
		}
		
		if (!@mysql_select_db($this->_access['database'], $this->connect)) {
			$this->connect = false;
			if ($r !== false) {
				return false;
			}
			
			exit('331');
		}
		unset($this->_access);
		
		return true;
	}
	
	public function verify() {
		return $this->connect;
	}
	
	public function close() {
		if (!$this->connect) {
			return false;
		}
		
		if ($this->result && @is_resource($this->result)) {
			@mysql_free_result($this->result);
		}
		
		if (is_resource($this->connect)) {
			return @mysql_close($this->connect);
		}
		
		return false;
	}
	
	public function query($query = '', $transaction = false) {
		if (is_array($query)) {
			foreach ($query as $sql) {
				$this->query($sql);
			}
			
			return;
		}
		
		unset($this->result);
		
		if (!empty($query)) {
			$this->queries++;
			$this->history[] = $query;
			
			if (!$this->result = @mysql_query($query, $this->connect)) {
				$this->error($query);
			}
		}
		
		if ($this->result) {
			unset($this->row[$this->result], $this->rowset[$this->result]);
			
			return $this->result;
		}
		
		return false;
	}
	
	public function query_limit($query, $total, $offset = 0) {
		if (empty($query)) {
			return false;
		}
		
		// if $total is set to 0 we do not want to limit the number of rows
		if (!$total) {
			$total = -1;
		}
		
		$query .= "\n LIMIT " . (($offset) ? $offset . ', ' . $total : $total);
		return $this->query($query);
	}
	
	public function transaction($status = 'begin') {
		switch ($status) {
			case 'begin':
				return @mysql_query('BEGIN', $this->connect);
				break;
			case 'commit':
				return @mysql_query('COMMIT', $this->connect);
				break;
			case 'rollback':
				return @mysql_query('ROLLBACK', $this->connect);
				break;
		}
		
		return true;
	}
	
	public function build($query, $assoc = false, $update_field = false) {
		if (!is_array($assoc)) {
			return false;
		}
		
		$fields = w();
		$values = w();
		
		switch ($query) {
			case 'INSERT':
				foreach ($assoc as $key => $var) {
					$fields[] = $key;
					
					if (is_null($var)) {
						$values[] = 'NULL';
					} elseif (is_string($var)) {
						$values[] = "'" . $this->escape($var) . "'";
					} else {
						$values[] = (is_bool($var)) ? intval($var) : $var;
					}
				}
				
				$query = ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
				break;
			case 'UPDATE':
			case 'SELECT':
				$values = w();
				
				foreach ($assoc as $key => $var) {
					if (is_null($var)) {
						$values[] = "$key = NULL";
					} elseif (is_string($var)) {
						if ($update_field && strpos($var, $key) !== false) {
							$values[] = $key . ' = ' . $this->escape($var);
						} else {
							$values[] = "$key = '" . $this->escape($var) . "'";
						}
					} else {
						$values[] = (is_bool($var)) ? "$key = " . intval($var) : "$key = $var";
					}
				}
				$query = implode(($query == 'UPDATE') ? ', ' : ' AND ', $values);
				break;
		}
		
		return $query;
	}
	
	public function num_queries() {
		return $this->queries;
	}
	
	public function numrows($query_id = 0) {
		if (!$query_id) {
			$query_id = $this->result;
		}
		
		return ($query_id) ? @mysql_num_rows($query_id) : false;
	}
	
	public function affectedrows() {
		return ($this->connect) ? @mysql_affected_rows($this->connect) : false;
	}
	
	public function numfields($query_id = 0) {
		if (!$query_id) {
			$query_id = $this->result;
		}
		
		return ($query_id) ? @mysql_num_fields($query_id) : false;
	}
	
	public function fieldname($offset, $query_id = 0) {
		if (!$query_id)
		{
			$query_id = $this->result;
		}
		
		return ($query_id) ? @mysql_field_name($query_id, $offset) : false;
	}
	
	public function fieldtype($offset, $query_id = 0) {
		if (!$query_id) { 
			$query_id = $this->result;
		}
		
		return ($query_id) ? @mysql_field_type($query_id, $offset) : false;
	}
	
	public function fetchrow($result_type = MYSQL_BOTH) {
		$query_id = $this->result;
		
		if (!$query_id) {
			return false;
		}
		
		$this->row['' . $query_id . ''] = @mysql_fetch_array($query_id, $result_type);
		return @$this->row['' . $query_id . ''];
	}
	
	public function fetchrowset($result_type = MYSQL_BOTH) {
		$query_id = $this->result;
		
		if (!$query_id) {
			return false;
		}
		
		unset($this->rowset[$query_id]);
		unset($this->row[$query_id]);
		
		$result = w();
		while ($this->rowset['' . $query_id . ''] = @mysql_fetch_array($query_id, $result_type)) {
			$result[] = $this->rowset['' . $query_id . ''];
		}
		return $result;
	}
	
	public function fetchfield($field, $rownum = -1, $query_id = 0) {
		if (!$query_id) {
			$query_id = $this->result;
		}
		
		if (!$query_id) {
			return false;
		}
		
		if ($rownum > -1) {
			$result = @mysql_result($query_id, $rownum, $field);
		} else {
			if (empty($this->row[$query_id]) && empty($this->rowset[$query_id])) {
				if ($this->fetchrow()) {
					$result = $this->row['' . $query_id . ''][$field];
				}
			} else {
				if ($this->rowset[$query_id]) {
					$result = $this->rowset[$query_id][0][$field];
				} elseif ($this->row[$query_id]) {
					$result = $this->row[$query_id][$field];
				}
			}
		}
		
		return (isset($result)) ? $result : false;
	}
	
	public function rowseek($rownum, $query_id = 0) {
		if (!$query_id) {
			$query_id = $this->result;
		}
		
		return ($query_id) ? @mysql_data_seek($query_id, $rownum) : false;
	}
	
	public function nextid() {
		return ($this->connect) ? @mysql_insert_id($this->connect) : false;
	}
	
	public function freeresult($query_id = false) {
		if (!$query_id) {
			$query_id = $this->result;
		}
		
		if (!$query_id) {
			return false;
		}
		
		unset($this->row[$query_id]);
		unset($this->rowset[$query_id]);
		$this->result = false;
		
		@mysql_free_result($query_id);
		return true;
	}
	
	public function escape($msg) {
		return mysql_real_escape_string($msg, $this->connect);
	}
	
	public function cache($a_sql, $sid = '', $private = true) {
		global $user;
		
		$filter_values = array($sid);
		
		$sql = 'SELECT cache_query
			FROM _search_cache
			WHERE cache_sid = ?';
		
		if ($private) {
			$sql .= ' AND cache_uid = ?';
			$filter_values[] = $bio->v('bio_id');
		}
		
		$query = _field(sql_filter($sql, $filter_values), 'cache_query', '');
		
		if (!empty($sid) && empty($query)) {
			_fatal();
		}
		
		if (empty($query) && !empty($a_sql)) {
			$sid = md5(unique_id());
			
			$insert = array(
				'cache_sid' => $sid,
				'cache_query' => $a_sql,
				'cache_uid' => $bio->v('bio_id'),
				'cache_time' => time()
			);
			$sql = 'INSERT INTO _search_cache' . $this->build('INSERT', $insert);
			$this->query($sql);
			
			$query = $a_sql;
		}
		
		$all_rows = 0;
		if (!empty($query)) {
			$result = $this->query($query);
			
			$all_rows = $this->numrows($result);
			$this->freeresult($result);
		}
		
		$has_limit = false;
		if (preg_match('#LIMIT ([0-9]+)(\, ([0-9]+))?#is', $query, $limits)) {
			$has_limit = $limits[1];
		}
		
		return array('sid' => $sid, 'query' => $query, 'limit' => $has_limit, 'total' => $all_rows);
	}
	
	public function cache_limit(&$arr, $start, $end = 0) {
		if ($arr['limit'] !== false) {
			$arr['query'] = preg_replace('#(LIMIT) ' . $arr['limit'] . '#is', '\\1 ' . $start, $arr['query']);
		} else {
			$arr['query'] .= ' LIMIT ' . $start . (($end) ? ', ' . $end : '');
		}
		
		return;
	}
	
	public function history() {
		return $this->history;
	}
	
	public function set_error($error = -1) {
		if ($error !== -1)
		{
			$this->noerror = $error;
		}
		
		return $this->noerror;
	}
	
	public function error($sql = '') {
		$sql_error = @mysql_error($this->connect);
		$sql_errno = @mysql_errno($this->connect);
		
		if (!$this->noerror) {
			fatal_error(507, '', '', array('sql' => $sql, 'message' => $sql_error), $sql_errno);
		}
		
		return array('message' => $sql_error, 'code' => $sql_errno);
	}
}

?>