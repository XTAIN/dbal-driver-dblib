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
 * ASE Statement.
 *
 * @since 2.6
 * @author Maximilian Ruta <mr@xtain.net>
 */
abstract class EmulatedPreparedStatement implements IteratorAggregate, Statement
{
    /**
     * The ASE Connection object.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * The SQL statement to execute.
     *
     * @var string
     */
    protected $sql;

    /**
     * Parameters to bind.
     *
     * @var array
     */
    protected $params = array();

    /**
     * @param Connection  $connection
     * @param string      $sql
     * @param array       $driverOptions
     */
    public function __construct(Connection $connection, $sql)
    {
        $this->connection = $connection;
        $this->sql = $sql;
    }

    /**
     * @param mixed $value
     * @param int   $type
     *
     * @return mixed
     */
    public static function guessType($value)
    {
        if (is_scalar($value)) {
            if (is_int($value) || is_float($value)) {
                return \PDO::PARAM_INT;
            } else if (is_bool($value)) {
                return \PDO::PARAM_BOOL;
            }
        }

        return \PDO::PARAM_STR;
    }

    /**
     * @param mixed $value
     * @param int   $type
     *
     * @return mixed
     */
    public static function fixType($value, $type = \PDO::PARAM_STR)
    {
        switch ($type) {
            case \PDO::PARAM_NULL:
                return null;
            case \PDO::PARAM_INT:
                $float = floatval($value);
                $int = intval($float);
                if ($float && $int != $float) {
                    return $float;
                }

                return $int;
            case \PDO::PARAM_BOOL:
                return $value ? 1 : 0;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        return $this->bindParam($param, $value, $type, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null)
    {
        if ($type === null) {
            $type = $this->guessType($variable);
        }

        if ($type === \PDO::PARAM_LOB && is_resource($variable)) {
            $this->params[$column] = array(stream_get_contents($variable), $type);
        } else {
            $this->params[$column] = array($this->fixType($variable, $type), $type);
        }
    }

    /**
     * @param string $sql
     * @param array $params
     * @return string
     */
    protected function interpolateQuery($sql, $params)
    {
        $quotedNamedParams = array();
        $quotedNumberedParams = array();
        $patternParts = array();
        $patternParts[] = preg_quote('?', '/');
        foreach ($params as $name => $data) {
            list($value, $type) = $data;

            $value = $this->connection->quote($value, $type);

            if (is_numeric($name)) {
                $quotedNumberedParams[$name] = $value;
            } else {
                $quotedNamedParams[$name] = $value;
            }

            $patternParts[] = preg_quote($name, '/');
        }

        $i = 0;

        $sql = preg_replace_callback(
            '/' . implode('|', $patternParts) . '/',
            function ($match) use(&$i, $quotedNumberedParams, $quotedNamedParams) {
                $match = $match[0];

                if ($match == '?') {
                    $i++;
                    if (isset($quotedNumberedParams[$i])) {
                        return $quotedNumberedParams[$i];
                    }
                } elseif (isset($quotedNamedParams[$match])) {
                    return $quotedNamedParams[$match];
                }

                return $match;
            },
            $sql
        );

        return $sql;
    }

    public function __toString()
    {
        return $this->interpolateQuery($this->sql, $this->params);
    }
}
