<?php

/**
 * Class Database
 *
 * Mysql Wrapper
 */
class Database
{

    /**
     * @var mysqli
     */
    private $_mysqli;

    /**
     * @var mysqli_result
     */
    private $_result;

    private $_query;

    private $_timer;

    private $_host;
    private $_name;
    private $_user;
    private $_pass;

    private $_char;
    private $_lang;

    private $_exceptionClass = 'Exception';

    /**
     * Database constructor
     *
     * @param string $DBHost
     * @param string $DBName
     * @param string $DBUser
     * @param string $DBPass
     *
     * @param string $char
     * @param string $lang
     *
     * @throws Exception
     */
    public function __construct($DBHost = null, $DBName = null, $DBUser = null, $DBPass = null, $char = 'utf8', $lang = 'ru_RU')
    {
        if (isset($DBHost)) $this->_host = $DBHost;
        if (isset($DBName)) $this->_name = $DBName;
        if (isset($DBUser)) $this->_user = $DBUser;
        if (isset($DBPass)) $this->_pass = $DBPass;

        if (!empty($char)) $this->_char = $char;
        if (!empty($lang)) $this->_lang = $lang;

        $this->connect();
    }

    /**
     * Database destructor
     */
    public function __destruct()
    {
        if ($this->_mysqli) $this->_mysqli->close();
    }

    /**
     * @throws Exception
     */
    private function connect()
    {
        @$this->_mysqli = new mysqli($this->_host, $this->_user, $this->_pass, $this->_name);

        if (!$this->_mysqli) {
            $this->error("Mysql connect error [{$this->_mysqli->connect_errno}]: {$this->_mysqli->connect_error}");
        }

        $this->_mysqli->query("SET lc_time_names = '{$this->_lang}'");
        $this->_mysqli->set_charset($this->_char);
    }

    /**
     * @throws Exception
     */
    private function checkConnect()
    {
        if (!$this->_mysqli->ping()) {
            $this->connect();
        }
    }

    /**
     * @param string $sql
     * @param mixed  $params
     *
     * @return string
     */
    private function prepareQuery($sql, $params)
    {
        if (is_array($params)) {
            foreach ($params as &$param) {
                $param = $this->escapeSimple($param);
            }
        } else {
            $params = $this->escapeSimple($params);
        }

        $query = vsprintf($sql, $params);

        return $query;
    }

    /**
     * @param string $sql
     *
     * @throws Exception
     */
    private function processQuery($sql)
    {
        // reset previous result
        $this->_result = null;

        $this->checkConnect();

        $timeStart = microtime(true);
        $this->_result = $this->_mysqli->query($sql);
        $this->_timer = microtime(true) - $timeStart;

        if (!$this->_result) {
            $this->error("invalid sql query: {$sql}. Mysql error [{$this->_mysqli->errno}]: {$this->_mysqli->error}");
        }
    }

    /**
     * @param string $str
     *
     * @return string
     */
    private function escapeSimple($str)
    {
        $str = $this->_mysqli->real_escape_string($str);

        return $str;
    }

    private function processFetch()
    {
        return $this->_result->fetch_assoc();
    }

    public function freeResult()
    {
        $this->_result->free();
    }

    public function getNumRows()
    {
        return $this->_result->num_rows;
    }

    public function getInsertId()
    {
        return $this->_mysqli->insert_id;
    }

    /** ---------------------------- **/

    public function getData()
    {
        if (!$this->_result) return false;

        $data = array();
        while ($row = $this->processFetch()) {
            $data[] = $row;
        }

        $this->freeResult();

        return $data;
    }

    public function getDataRow()
    {
        if (!$this->_result) return false;
        if ($this->getNumRows() != 1) return false;

        $data = $this->processFetch();

        $this->freeResult();

        return $data;
    }

    public function getDataCol()
    {
        if (!$this->_result) return false;

        $data = array();
        while ($row = $this->processFetch()) {
            $data[] = reset($row);
        }

        $this->freeResult();

        return $data;
    }

    public function getDataCell()
    {
        if (!$this->_result) return false;
        if ($this->getNumRows() != 1) return false;

        $data = array_values($this->processFetch());

        $this->freeResult();

        return $data[0];
    }

    /** ---------------------------- **/

    /**
     * Custom query
     *
     * @param string $sql
     * @param array  $params
     *
     * @return void
     *
     * @throws Exception
     */
    public function query($sql, $params = array())
    {
        $query = $this->prepareQuery($sql, $params);
        $this->processQuery($query);
    }

    /**
     * Select data from database
     *
     * @param string  $table
     * @param string  $columns
     * @param array   $conditions
     * @param array   $sorting
     * @param integer $limit
     * @param integer $offset
     *
     * @return void
     * @throws Exception
     */
    public function select($table, $columns = '*', $conditions = null, $sorting = null, $limit = null, $offset = null)
    {
        $table = $this->prepareTable($table);
        $columns = $this->prepareColumns($columns);
        $where = $this->prepareConditions($conditions);
        $order = $this->prepareSorting($sorting);
        $limit = $this->prepareLimit($limit, $offset);

        $query = "SELECT {$columns} FROM {$table}";
        $query .= !empty($where) ? $where : '';
        $query .= !empty($order) ? $order : '';
        $query .= !empty($limit) ? $limit : '';

        $this->processQuery($query);
    }

    /**
     * Insert data to database
     *
     * @param string $table
     * @param array  $data
     *
     * @return void
     * @throws Exception
     */
    public function insert($table, $data)
    {
        $table = $this->prepareTable($table);
        $data = $this->prepareData($data);

        $query = "INSERT INTO {$table} SET {$data}";

        $this->processQuery($query);
    }

    /**
     * Update data in database
     *
     * @param string $table
     * @param array  $data
     * @param array  $conditions
     *
     * @return void
     * @throws Exception
     */
    public function update($table, $data, $conditions = null)
    {
        $table = $this->prepareTable($table);
        $data = $this->prepareData($data);
        $where = $this->prepareConditions($conditions);

        $query = "UPDATE {$table} SET {$data}";
        $query .= !empty($where) ? $where : '';

        $this->processQuery($query);
    }

    /**
     * Delete data from database
     *
     * @param string $table
     * @param array  $conditions
     *
     * @return void
     * @throws Exception
     */
    public function delete($table, $conditions = null)
    {
        $table = $this->prepareTable($table);
        $where = $this->prepareConditions($conditions);

        $query = "DELETE FROM {$table}";
        $query .= !empty($where) ? $where : '';

        $this->processQuery($query);
    }

    /** ---------------------------- **/

    /**
     * Prepare table name
     *
     * @param string $table
     *
     * @return string
     * @throws Exception
     */
    private function prepareTable($table)
    {
        if (empty($table)) {
            $this->error('Empty table');
        }

        $table = "`{$this->escapeSimple($table)}`";

        return $table;
    }

    /**
     * Prepare columns
     *
     * @param string|array $columns
     *
     * @return string
     * @throws Exception
     */
    private function prepareColumns($columns)
    {
        if ($columns == '*') return $columns;

        if (empty($columns)) {
            $this->error('Empty columns');
        }

        if (!is_array($columns)) {
            $columns = explode(',', $columns);
        }

        $columnsStr = '';
        $comma = '';
        foreach ($columns as $column) {
            $column = trim($column);
            $columnsStr .= "{$comma}`{$this->escapeSimple($column)}`";
            $comma = ', ';
        }

        return $columnsStr;
    }

    /**
     * Prepare data
     *
     * @param array $data
     *
     * @return string
     * @throws Exception
     */
    private function prepareData($data)
    {
        if (empty($data)) {
            $this->error('Empty data');
        }

        $dataStr = '';

        if (is_array($data)) {
            $comma = '';
            foreach ($data as $param => $value) {
                $dataStr .= "{$comma}`{$this->escapeSimple($param)}` = '{$this->escapeSimple($value)}'";
                $comma = ', ';
            }
        } else {
            $this->error('Data must be array');
        }

        return $dataStr;
    }

    /**
     * Prepare conditions
     *
     * @param array $conditions
     *
     * @return string
     * @throws Exception
     */
    private function prepareConditions($conditions)
    {
        $where = '';

        if (!isset($conditions)) return $where;

        if (is_array($conditions)) {
            $and = '';
            foreach ($conditions as $param => $value) {
                $where .= "{$and}`{$this->escapeSimple($param)}` = '{$this->escapeSimple($value)}'";
                $and = " AND ";
            }
            $where = " WHERE {$where}";
        } else {
            $this->error('Conditions must be array');
        }

        return $where;
    }

    /**
     * Prepare sorting params
     *
     * @param $sorting
     *
     * @return string
     * @throws Exception
     */
    private function prepareSorting($sorting)
    {
        $order = '';

        if (!isset($sorting)) return $order;

        if (is_array($sorting)) {
            $comma = '';
            foreach ($sorting as $param => $value) {
                if ($value !== 'DESC') $value = 'ASC';
                $order .= "{$comma}`{$this->escapeSimple($param)}` {$value}";
                $comma = ', ';
            }
            $order = " ORDER BY {$order}";
        } else {
            $this->error('Orders must be array');
        }

        return $order;
    }

    /**
     * Prepare limit params
     *
     * @param integer $rows
     * @param integer $offset
     *
     * @return string
     */
    private function prepareLimit($rows, $offset = null)
    {
        $limit = '';

        if (isset($rows)) {
            $rows = intval($rows);

            if (isset($offset)) {
                $offset = intval($offset);
                $limit = " LIMIT {$offset}, {$rows}";
            } else {
                $limit = " LIMIT {$rows}";
            }
        }

        return $limit;
    }

    /**
     * Throw error message
     *
     * @param string $message
     *
     * @throws Exception
     */
    private function error($message)
    {
        throw new $this->_exceptionClass($message);
    }

    /**
     * Set Exception handler class name
     *
     * @param string $handler
     */
    public function setExceptionHandler($handler)
    {
        $this->_exceptionClass = $handler;
    }
}