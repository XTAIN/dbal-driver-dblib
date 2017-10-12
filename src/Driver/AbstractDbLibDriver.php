<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\PDOException;

class AbstractDbLibDriver
{
    /**
     * @param array $params
     * @param array $driverOptions
     * @return string
     */
    public static function constructPdoDsn(array $params, array $driverOptions)
    {
        if (isset($driverOptions['protocol_version']) && !isset($params['protocol_version'])) {
            $params['protocol_version'] = $driverOptions['protocol_version'];
        }

        $dsn = 'dblib:host=';

        if (isset($params['host'])) {
            $dsn .= $params['host'];
        }

        if (isset($params['port']) && !empty($params['port'])) {
            $dsn .= ':' . $params['port'];
        }

        if (isset($params['dbname'])) {
            $dsn .= ';dbname=' .  $params['dbname'];
        }

        if (isset($params['charset'])) {
            $dsn .= ';chartset=' . $params['charset'];
        }

        if (isset($params['protocol_version'])) {
            $dsn .= ';version=' . $params['protocol_version'];
        }

        return $dsn;
    }

    /**
     * @param \PDO $PDO
     * @param array $args
     *
     * @return \PDOStatement
     * @throws \Exception
     */
    public static function emulateQuery(\PDO $PDO, array $args)
    {
        $argsCount = count($args);

        try {
            $stmt = $PDO->prepare($args[0]);

            if ($stmt === false) {
                throw new \Exception('prepare failed');
            }

            if ($argsCount == 4) {
                $stmt->setFetchMode($args[1], $args[2], $args[3]);
            }

            if ($argsCount == 3) {
                $stmt->setFetchMode($args[1], $args[2]);
            }

            if ($argsCount == 2) {
                $stmt->setFetchMode($args[1]);
            }

            $stmt->execute();
            return $stmt;
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

}