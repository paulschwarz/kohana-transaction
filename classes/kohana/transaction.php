<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Transactional unit of work executor.
 * 
 * @example 
 * <pre>
 * Transaction::factory($this->_db_group)
 *     ->rollback_exclusion(array('Exception'))
 *     ->call(array($this, 'add_folder'))
 *     ->with($group, $this->group_code, $_POST)
 *     ->execute();
 * </pre>
 *
 * @author     Paul Schwarz <paulsschwarz@gmail.com>
 * 
 */

class Kohana_Transaction {
	
	private $_db, $_work, $_args, $_dont_rollback_for;
	
	private function __construct($_db)
    {
		$this->_db = $_db;
	}
	
	/**
	 * Create a Transaction instance to perform a transactional unit of work.
	 * @param string or Database instance $database_group the name of the database group in which
	 * to ensure transactionality.
     * @return Transaction
     * @throws Exception
	 */
	public static function factory($database_group)
    {
		if ($database_group == null)
        {
			throw new Exception('database instance not set');
		}

        if (is_string($database_group))
        {
            return new Transaction(Database::instance($database_group));
        }
        else
        {
            return new Transaction($database_group);
        }
	}
	
	/**
	 * Set the rollback exclusion policy by supplying an array of exception 
	 * class names upon which the transaction will not be rolled back.
	 * @param array $dont_rollback_for 
	 * @example array('Kohana_Exception', 'OtherException')
     * @return Transaction
	 */
	public function rollback_exclusion(array $dont_rollback_for)
    {
		$this->_dont_rollback_for = $dont_rollback_for;
		return $this;
	}
	
	/**
	 * Supply a callback function to be executed as the transactional unit of work.
	 * @param array $work callback function
     * @return Transaction
	 */
	public function call(array $work)
    {
		$work[1] = 'transactional_'.$work[1];
		$this->_work = $work;
		return $this;
	}
	
	/**
	 * Bind the arguments that will be available to the callback function
     * @return Transaction
	 */
	public function with()
    {
		$this->_args = func_get_args();
		return $this;
	}
	
	/**
	 * Execute the transaction
	 * @throws Exception
     * @return result
	 */
	public function execute()
    {
		if (!is_callable($this->_work))
        {
			throw new Exception('"'.$this->_work[1].'" is not a valid callback function (must be public)');
		}
		
		if ($this->_args == NULL)
        {
			$this->_args = array();
		}
		
		return $this->_execute($this->_db, $this->_work, $this->_args);
	}


    /**
     * Execute a transactional unit of work.
     *
     * @param Database $database_instance the database instance shared by the transaction and the unit of work
     * @param function $work callback to execute the transactional unit of work
     * @param $args
     * @throws Exception
     * @return result
     */
	private function _execute(Database $database_instance, $work, $args)
    {
		$database_instance->begin();
		
		try
        {
			// Do the work as a transaction
			$result = call_user_func_array($work, $args);
			// Transaction successful, commit the changes
			$database_instance->commit();
		}
        catch (Exception $e)
        {
			// Check if this exception type is included in the rollback policy
			if (!$this->in_rollback_exclusion_policy($e))
            {
				// Transaction failed. Roll back changes
				$database_instance->rollback();
				
				Log::instance()->add(Log::DEBUG, 'Rolled back transaction: :method', array(
				    ':method' => $work[1],
				));
			}
            else
            {
				// Transaction successful, commit the changes
				$database_instance->commit();
			}
			throw $e;
		}
		return $result;
	} 
	
	/**
	 * Test if the caught exception is found in the rollback exclusion policy
	 * @param Exception $e the caught exception
     * @return boolean
	 */
	private function in_rollback_exclusion_policy(Exception $e)
    {
		$exception = get_class($e);
		
		if (isset($this->_dont_rollback_for) && is_array($this->_dont_rollback_for))
        {
			foreach($this->_dont_rollback_for as $rollback_type)
            {
				if ($exception == $rollback_type || is_subclass_of($exception, $rollback_type))
                {
                    return TRUE;
                }
			}
		}
	}
		
}
