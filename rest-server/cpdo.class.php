<?php

class CPDO extends PDO {
	const INSERT = 'INSERT';
	const REPLACE = 'REPLACE';
	const INSERT_IGNORE = 'INSERT IGNORE';

	public function quote($string,$paramType = null)
	{
		//avoids quoting ints which results in errors in limit and offset causes
		if(is_int($string))
			return $string;
		else
			return parent::quote($string,$paramType);
	}

	public function quoteInto($text, $value, $type = null, $count = null)
	{
		if (is_array($value)) {
			foreach ($value as &$val) {
				$val = $this->quote($val, $type);
			}

			$replace = implode(', ', $value);
		}
		else
			$replace = $this->quote($value, $type);

		if ($count === null) {
			return str_replace('?', $replace, $text);
		} else {
			while ($count > 0) {
				if (strpos($text, '?') !== false) {
					$text = substr_replace($text, $replace, strpos($text, '?'), 1);
				}
				--$count;
			}
			return $text;
		}
	}

	protected function _quoteIdentifier($value)
	{
		$q = $this->getQuoteIdentifierSymbol();
		return ($q . str_replace("$q", "$q$q", $value) . $q);
	}

	protected function _quoteIdentifierAs($ident, $alias = null, $auto = false, $as = ' AS ')
	{
		if (is_string($ident)) {
			$ident = explode('.', $ident);
		}
		if (is_array($ident)) {
			$segments = array();
			foreach ($ident as $segment) {
				$segments[] = $this->_quoteIdentifier($segment, $auto);
			}
			if ($alias !== null && end($ident) == $alias) {
				$alias = null;
			}
			$quoted = implode('.', $segments);
		} else {
			$quoted = $this->_quoteIdentifier($ident, $auto);
		}

		if ($alias !== null) {
			$quoted .= $as . $this->_quoteIdentifier($alias, $auto);
		}
		return $quoted;
	}
	public function fetchOne($sql, $bind = array())
	{
		$stmt = $this->query($sql, $bind);
		$result = $stmt->fetchColumn(0);
		return $result;
	}

	public function fetchCol($sql, $bind = array()) {
		$stmt = $this->query($sql, $bind);
		$result = $stmt->fetchAll(FETCH_COLUMN, 0);
		return $result;
	}

	public function fetchObj($sql, $bind = array()) {
		$stmt = $this->query($sql, $bind);
		$result = $stmt->fetchObject();
		return $result;
	}

	public function fetchRow($sql, $bind = array(), $fetchMode = 2)
	{
		$stmt = $this->query($sql, $bind);
		$result = $stmt->fetch($fetchMode);
		return $result;
	}

	public function fetchAll($sql, $bind = array(), $fetchMode = 2)
	{
		$stmt = $this->query($sql, $bind);
		$result = $stmt->fetchAll($fetchMode);
		return $result;
	}

	public function isValidSyntax($sql, $bind = array(), $return_details = false)
	{
		// handle sql that contains variable placeholders
		$placeholders = array(":start_date",":end_date");
		$sql = str_replace($placeholders, "0", $sql);
		$sql = "explain $sql";
		try
		{
			$this->query($sql,$bind);
			return true;
		}
		catch(Exception $ex)
		{
			if ($return_details) {
				return $ex->getMessage();
			} else {
				return false;
			}
		}
	}

	public function query($sql,$bind = array())
	{

		if (!is_array($bind)) {
			$bind = array($bind);
		}

		// prepare and execute the statement with profiling
		$stmt = $this->prepare($sql);
		$retval = $stmt->execute($bind);

		if ($retval === false) {
			// set the error code if it's a numeric value
			$err_code = is_numeric($stmt->errorCode()) ? $stmt->errorCode() : 0;
			throw new PDOException("PDO statement execute error : " . $stmt->errorInfo()[2] . " Error code: ". $stmt->errorCode(), $err_code);
		}

		// return the results embedded in the prepared statement object
		$stmt->setFetchMode(2);

		return $stmt;
	}

	public function quoteIdentifier($ident)
	{
		return $this->_quoteIdentifierAs($ident, null);
	}

	protected function _whereExpr($where)
	{
		if (empty($where)) {
			return $where;
		}
		if (!is_array($where)) {
			$where = array($where);
		}
		foreach ($where as $cond => &$term) {
			// is $cond an int? (i.e. Not a condition)
			if (is_int($cond)) {
				// $term is the full condition
				$term = $term;
			} else {
				// $cond is the condition with placeholder,
				// and $term is quoted into the condition
				$term = $this->quoteInto($cond, $term);
			}
			$term = '(' . $term . ')';
		}
		$where = implode(' AND ', $where);
		return $where;
	}

	public function whereExprFromFields($fields)
	{
		$where = [];
		foreach ($fields as $field => $value) {
			$quotedField = $this->quoteIdentifier($field);
			if (is_array($value)) {
				$where[$quotedField.' IN (?)'] = $value;
			}
			else if ($value === null) {
				$where[] = $quotedField.' IS NULL';
			}
			else {
				$where[$quotedField.' = ?'] = $value;
			}
		}

		return $this->_whereExpr($where);
	}

	public function delete($table, $where = '')
	{
		$where = $this->_whereExpr($where);

		/**
		 * Build the DELETE statement
		 */
		$sql = "DELETE FROM "
			. $this->quoteIdentifier($table, true)
			. (($where) ? " WHERE $where" : '');

		/**
		 * Execute the statement and return the number of affected rows
		 */

		$stmt = $this->query($sql);
		$result = $stmt->rowCount();
		return $result;
	}

	public function update($table, array $bind, $where = '')
	{
		/**
		 * Build "col = ?" pairs for the statement,
		 * except for Zend_Db_Expr which is treated literally.
		 */
		$set = array();
		$i = 0;
		foreach ($bind as $col => $val) {
			$val = '?';
			$set[] = $this->quoteIdentifier($col, true) . ' = ' . $val;
		}

		$where = $this->_whereExpr($where);

		/**
		 * Build the UPDATE statement
		 */
		$sql = "UPDATE "
			. $this->quoteIdentifier($table, true)
			. ' SET ' . implode(', ', $set)
			. (($where) ? " WHERE $where" : '');


		$stmt = $this->query($sql, array_values($bind));
		$result = $stmt->rowCount();
		return $result;
	}

	public function getQuoteIdentifierSymbol()
	{
		$driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
		if(strtolower($driver) == "mysql")
			return "`";
		else
			return '"';
	}

	public function insert($table, array $bind) {
		return $this->_insert($table, $bind, self::INSERT);
	}

	public function replace($table, array $bind) {
		return $this->_insert($table, $bind, self::REPLACE);
	}

	public function insertIgnore($table, array $bind) {
		return $this->_insert($table, $bind, self::INSERT_IGNORE);
	}

	private function _insert($table, array $bind, string $operation)
	{
		// extract and quote col names from the array keys
		$cols = array();
		$vals = array();
		$i = 0;
		foreach ($bind as $col => $val) {
			$cols[] = $this->quoteIdentifier($col, true);
			$vals[] = '?';
		}

		// build the statement
		$sql = "$operation INTO " . $this->quoteIdentifier($table, true) . ' (' . implode(', ', $cols) . ') ' . 'VALUES (' . implode(', ', $vals) . ')';


				// execute the statement and return the number of affected rows
				$bind = array_values($bind);
				$stmt = $this->query($sql, $bind);
				$result = $stmt->rowCount();
				return $result;
				}

				protected function _setValuesExpr($setValues)
				{
				if (empty($setValues)) {
				return $setValues;
				}
				if (!is_array($setValues)) {
				$setValues = [$setValues];
				}
				foreach ($setValues as $cond => &$term) {
				// is $cond an int? (i.e. Not a condition)
				if (!is_int($cond)) {
					// $cond is the condition with placeholder,
					// and $term is quoted into the condition
					$term = $this->quoteInto($cond, $term);
				}
				}
				$setValues = implode(', ', $setValues);
				return $setValues;
				}

		protected function _generateUpdateValuesSql(array $bind, $overrideUpdateValues = null)
		{
			if (isset($overrideUpdateValues)) {
				// Overriding the values to change when duplicate key
				$updateValuesSql = $this->_setValuesExpr($overrideUpdateValues);
			} else {
				// Update set's all the values we'd be inserting
				$updateValues = [];
				foreach ($bind as $col => $val) {
					$quotedColumn = $this->quoteIdentifier($col, true);

					$updateValues[] = $quotedColumn.' = VALUES('.$quotedColumn.')';
							}
							$updateValuesSql = $this->_setValuesExpr($updateValues);
							}

							return $updateValuesSql;
							}

							public function insertOnDuplicateKeyUpdate($table, array $bind, $overrideUpdateValues = null)
							{
							// extract and quote col names from the array keys
							$cols = [];
							$vals = [];
							foreach ($bind as $col => $val) {
							$cols[] = $this->quoteIdentifier($col, true);
							$vals[] = '?';
							}

							$updateValuesSql = $this->_generateUpdateValuesSql($bind, $overrideUpdateValues);

							$colsSql = ' (' . implode(', ', $cols) . ') ';
							$valsSql = ' VALUES (' . implode(', ', $vals) . ') ';

							// build the statement
							$sql = 'INSERT INTO ' . $this->quoteIdentifier($table, true) . $colsSql . $valsSql . ' ON DUPLICATE KEY UPDATE '.$updateValuesSql;

							// execute the statement and return the number of affected rows
							$bind = array_values($bind);
							$stmt = $this->query($sql, $bind);
							$result = $stmt->rowCount();
							return $result;
							}

					public function insertMultiple($table, array $rows, array $options = [])
					{
						if (empty($rows)) {
							return 0;
						}

						// Get the first row
						$first_row = reset($rows);
						if (!$first_row) {
							$first_row = [];
						}

						// Get the columns
						$cols = [];
						foreach ($first_row as $col => $val) {
							$cols[] = $this->quoteIdentifier($col, true);
						}

						// Build bind and statement
						$bind = [];
						$question_marks = [];
						foreach ($rows as $row) {
							$question_marks[] = '('.implode(',', array_fill(0, count($row), '?')).')';
							$bind = array_merge($bind, array_values($row));
						}

						// Support for on duplicate key update
						$onDuplicateKeyUpdate = '';
						$on_duplicate_key_update = $options['on_duplicate_key_update'] ?? false;
						if ($on_duplicate_key_update) {
							$overrideUpdateValues = $options['overrideUpdateValues'] ?? null;
							$updateValuesSql = $this->_generateUpdateValuesSql($first_row, $overrideUpdateValues);
							$onDuplicateKeyUpdate = ' ON DUPLICATE KEY UPDATE '.$updateValuesSql;
						}

						$operation = $options['operation'] ?? self::INSERT;

						$sql = $operation.' INTO '.$this->quoteIdentifier($table, true).' (' . implode(',', $cols) . ') VALUES ' . implode(',', $question_marks) . $onDuplicateKeyUpdate;

						// Run query
						$this->beginTransaction();
						try {
							$stmt = $this->query($sql, $bind);
							$result = $stmt->rowCount();
						}
						catch (Exception $e) {
							$this->rollBack();
							throw $e;
						}

						$this->commit();

						return $result;
					}

					public function getConfig()
					{
						$dbname = $this->fetchOne("select database()");
						return array("dbname"=>$dbname);

					}
}
