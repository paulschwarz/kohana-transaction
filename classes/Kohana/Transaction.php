<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Database transactional unit of work executor.
 * 
 * @example 
 * <pre>
 *
 * $name = 'World';
 *
 * $transaction = Transaction::factory($this->_database,
 *   function() use ($name)
 *   {
 *     return 'Hello '.$name;
 *   }
 * )->rollback_exclusion('My_Exception');
 *
 * echo $transaction();
 *
 * </pre>
 *
 * @author     Paul Schwarz <paulsschwarz@gmail.com>
 * 
 */

class Kohana_Transaction {

	protected $_db, $_work, $_dont_rollback_for;
	
	protected function __construct(Database $db, callable $work)
    {
		$this->_db = $db;
		$this->_work = $work;
	}

	/**
	 * Create a Transaction instance to perform a transactional unit of work.
	 *
	 * @static
	 * @param $database_group Database|string the database group in which to ensure transactionality.
	 * @param callable $work
	 * @return Transaction
	 * @throws Exception
	 */
	public static function factory($database_group, callable $work)
    {
		if ($database_group == null)
        {
			throw new Exception('database instance not set');
		}

        if (is_string($database_group))
        {
            return new Transaction(Database::instance($database_group), $work);
        }
        else
        {
            return new Transaction($database_group, $work);
        }
	}
	
	/**
	 * Set the rollback exclusion policy by supplying an array of exception 
	 * class names upon which the transaction will not be rolled back.
	 *
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
	 * Execute the transaction.
	 *
	 * @throws Exception
	 * @return result
	 */
	public function execute()
	{
		/*
		 * This invocation appears in the stack trace, therefore any details on the cause of an exception are available
		 * in the [1]st element of the backtrace. If you change the way the code operates in execute() or __invoke() you
		 * could impact the ability for logging the rollback exception's details.
		 */
		return $this->_execute($this->_db, $this->_work);
	}

	/**
	 * Execute the transaction.
	 *
	 * @throws Exception
	 * @return result
	 */
	public function __invoke()
	{
		/*
		 * This invocation appears in the stack trace, therefore any details on the cause of an exception are available
		 * in the [1]st element of the backtrace. If you change the way the code operates in execute() or __invoke() you
		 * could impact the ability for logging the rollback exception's details.
		 */
		return $this->_execute($this->_db, $this->_work);
	}

	/**
	 * Execute the transactional unit of work.
	 *
	 * @param Database $database_instance the database instance shared by the transaction and the unit of work.
	 * @param callable $work callback to execute the transactional unit of work.
	 * @return mixed
	 * @throws Exception
	 */
	protected function _execute(Database $database_instance, callable $work)
    {
		$database_instance->begin();
		
		try
        {
			// Do the work as a transaction.
			$result = call_user_func_array($work, []);

			// Transaction successful, commit the changes.
			$database_instance->commit();
		}
        catch (Exception $e)
        {
			// Check if this exception type is included in the rollback policy.
			if ( ! $this->in_rollback_exclusion_policy($e))
            {
				// Transaction failed. Roll back changes.
				$database_instance->rollback();

				// See the notes inside execute() and __invoke() for an explanation of why this is [1].
				$caller = debug_backtrace()[1];

				Log::instance()->add(Log::DEBUG, 'Rolled back for: :type :file::line. See log for stack trace.', [
					':type' => get_class($e),
					':file' => $caller['file'],
					':line' => $caller['line'],
				]);
			}
            else
            {
				// Transaction successful, commit the changes.
				$database_instance->commit();
			}
			throw $e;
		}
		return $result;
	} 
	
	/**
	 * Test if the caught exception is found in the rollback exclusion policy.
	 *
	 * @param Exception $e the caught exception
     * @return boolean
	 */
	protected function in_rollback_exclusion_policy(Exception $e)
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
