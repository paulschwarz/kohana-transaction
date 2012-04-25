<?php defined('SYSPATH') or die('No direct access allowed!'); 
 
class TransactionTest extends UnitTest_TestCase{

    public function test_transactional_commit()
    {
        $database = $this->getMock('Database', array(), array(), '', FALSE);

        $database->expects($this->once())->method('commit');

        $result = Transaction::factory($database)
            ->call(array($this, 'divide'))
            ->with(10, 2)
            ->execute();

        $this->assertEquals(5, $result);
    }

    /**
     * @expectedException ErrorException
     */
    public function test_transactional_rollback()
    {
        $database = $this->getMock('Database', array(), array(), '', FALSE);

        $database->expects($this->once())->method('rollback');

        Transaction::factory($database)
            ->call(array($this, 'divide'))
            ->with(10, 0)
            ->execute();
    }

    /**
     * @expectedException ErrorException
     */
    public function test_transactional_commit_exception()
    {
        $database = $this->getMock('Database', array(), array(), '', FALSE);

        $database->expects($this->never())->method('rollback');
        $database->expects($this->once())->method('commit');

        Transaction::factory($database)
            ->rollback_exclusion(array('ErrorException'))
            ->call(array($this, 'divide'))
            ->with(10, 0)
            ->execute();
    }

    /**
     * This is an example of a transactional function. This function can contain code that you wish to execute within
     * the database transaction.
     *
     * @param $a
     * @param $b
     * @return float
     */
    function transactional_divide($a, $b)
    {
        return $a / $b;
    }

}