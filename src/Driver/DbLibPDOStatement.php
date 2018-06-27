<?php

namespace Doctrine\DBAL\Driver;

class DbLibPDOStatement extends PDOStatement
{
    /**
     * @var self
     */
    protected static $lastActive;

    /**
     * @var array
     */
    protected $resultCache;

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        if ($type === null) {
            $type = EmulatedPreparedStatement::guessType($value);
        }

        return parent::bindValue(
            $param,
            EmulatedPreparedStatement::fixType($value, $type),
            $type
        );
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null, $driverOptions = null)
    {
        if ($type === null) {
            $type = EmulatedPreparedStatement::guessType($variable);
        }

        $variable = EmulatedPreparedStatement::fixType($variable, $type);
        return parent::bindParam($column, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        if (isset($this->resultCache)) {
            if (count($this->resultCache) == 0) {
                return false;
            }

            $item = array_values(array_shift($this->resultCache));

            return $item[$columnIndex];
        } else {
            return parent::fetchColumn($columnIndex);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        if (isset($this->resultCache)) {
            if ($fetchMode != \PDO::FETCH_ASSOC && $cursorOrientation !== \PDO::FETCH_ORI_NEXT || $cursorOffset !== 0) {
                throw new \RuntimeException('result caching is only implemented for PDO::FETCH_ASSOC');
            }

            if (count($this->resultCache) == 0) {
                return false;
            }

            return array_shift($this->resultCache);
        } else {
            return parent::fetch($fetchMode, $cursorOrientation, $cursorOffset);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        if (isset($this->resultCache)) {
            if ($fetchMode != \PDO::FETCH_ASSOC && $fetchArgument !== null || $ctorArgs !== null) {
                throw new \RuntimeException('result caching is only implemented for PDO::FETCH_ASSOC');
            }

            $data = $this->resultCache;
            $this->resultCache = array();
            return $data;
        } else {
            return parent::fetchAll($fetchMode, $fetchArgument, $ctorArgs);
        }
    }

    protected function cacheResults()
    {
        $this->resultCache = parent::fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ($params !== null) {
            foreach ($params as $key => $value) {
                if (is_int($key)) {
                    $key++;
                }

                $this->bindValue($key, $value, null);
            }
        }

        if (self::$lastActive instanceof self) {
            self::$lastActive->cacheResults();
        }

        $result = parent::execute();

        self::$lastActive = $this;

        // forward to the result set which is not empty
        try {
            while ($this->columnCount() == 0) {
                $this->fetchAll(\PDO::FETCH_NUM);
                if (!$this->nextRowset()) {
                    break;
                }
            }
        } catch (\Exception $e) {
            // continue
        }

        return $result;
    }
}