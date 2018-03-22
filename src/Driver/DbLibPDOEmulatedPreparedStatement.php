<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\Types\Type;
use PDO;
use IteratorAggregate;
use Doctrine\DBAL\Driver\Statement;
use Traversable;

/**
 * DBLib Emulated Prepared Statement.
 *
 * @since 2.6
 * @author Maximilian Ruta <mr@xtain.net>
 */
class DbLibPDOEmulatedPreparedStatement extends EmulatedPreparedStatement implements IteratorAggregate, Statement
{
    /**
     * @var array
     */
    private $driverOptions;

    /**
     * @var array
     */
    private $fetchMode = null;

    /**
     * @var string
     */
    protected $stmt = null;

    /**
     * @const string
     */
    const ATTR_STATEMENT_ORIGINAL = 'ATTR_STATEMENT_ORIGINAL';

    /**
     * @param Connection  $connection
     * @param string      $sql
     * @param array       $driverOptions
     */
    public function __construct(Connection $connection, $sql, array $driverOptions = array())
    {
        parent::__construct($connection, $sql);
        $this->driverOptions = $driverOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ($params) {
            $hasZeroIndex = array_key_exists(0, $params);
            foreach ($params as $key => $val) {
                $key = ($hasZeroIndex && is_numeric($key)) ? $key + 1 : $key;
                $this->bindValue($key, $val);
            }
        }

        $prepared = $this->interpolateQuery($this->sql, $this->params);

        $this->stmt = $this->connection->prepare($prepared, array_merge(
            $this->driverOptions,
            array(
                self::ATTR_STATEMENT_ORIGINAL => true
            )
        ));

        if ($this->fetchMode !== null) {
            call_user_func_array(array($this, 'setFetchMode'), $this->fetchMode);
        }

        return $this->stmt->execute();
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array(array(
            $this->stmt,
            $name
        ), $arguments);
    }

    public function __toString()
    {
        return $this->interpolateQuery($this->sql, $this->params);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();

        return new \ArrayIterator($data);
    }

    /**
     * @inheritDoc
     */
    public function closeCursor()
    {
        return $this->stmt->closeCursor();
    }

    /**
     * @inheritDoc
     */
    public function columnCount()
    {
        return $this->stmt->columnCount();
    }

    /**
     * @inheritDoc
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        if ($this->stmt !== null) {
            return $this->stmt->setFetchMode($fetchMode, $arg2, $arg3);
        } else {
            $this->fetchMode = array($fetchMode, $arg2, $arg3);
        }
    }

    /**
     * @inheritDoc
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        return $this->stmt->fetch($fetchMode, $cursorOrientation, $cursorOffset);
    }

    /**
     * @inheritDoc
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        return $this->stmt->fetchAll($fetchMode, $fetchArgument, $ctorArgs);
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->stmt->fetchColumn($columnIndex);
    }

    /**
     * @inheritDoc
     */
    function errorCode()
    {
        return $this->stmt->errorCode();
    }

    /**
     * @inheritDoc
     */
    function errorInfo()
    {
        return $this->stmt->errorInfo();
    }

    /**
     * @inheritDoc
     */
    function rowCount()
    {
        return $this->stmt->rowCount();
    }
}
