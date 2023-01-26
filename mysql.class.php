<?php
	/**
 * @author col.shrapnel@gmail.com
 * @link http://phpfaq.ru/safemysql
 * 
 * Safe and convenient way to handle SQL queries utilizing type-hinted placeholders.
 * 
 * Key features
 * - set of helper functions to get the desired result right out of query, like in PEAR::DB
 * - conditional query building using parse() method to build queries of whatever comlexity, 
 *   while keeping extra safety of placeholders
 * - type-hinted placeholders
 * 
 *  Type-hinted placeholders are great because 
 * - safe, as any other [properly implemented] placeholders
 * - no need for manual escaping or binding, makes the code extra DRY
 * - allows support for non-standard types such as identifier or array, which saves A LOT of pain in the back.
 * 
 * Supported placeholders at the moment are:
 * 
 * ?s ("string")  - strings (also DATE, FLOAT and DECIMAL)
 * ?i ("integer") - the name says it all 
 * ?n ("name")    - identifiers (table and field names) 
 * ?a ("array")   - complex placeholder for IN() operator  (substituted with string of 'a','b','c' format, without parentesis)
 * ?u ("update")  - complex placeholder for SET operator (substituted with string of `field`='value',`field`='value' format)
 * and
 * ?p ("parsed") - special type placeholder, for inserting already parsed statements without any processing, to avoid double parsing.
 * 
 * Connection:
 *
 * $db = new SafeMySQL(); // with default settings
 * 
 * $opts = array(
 *		'user'    => 'user',
 *		'pass'    => 'pass',
 *		'db'      => 'db',
 *		'charset' => 'latin1'
 * );
 * $db = new SafeMySQL($opts); // with some of the default settings overwritten
 * 
 * Alternatively, you can just pass an existing mysqli instance that will be used to run queries 
 * instead of creating a new connection.
 * Excellent choice for migration!
 * 
 * $db = new SafeMySQL(['mysqli' => $mysqli]);
 * 
 * Some examples:
 * 
 * $name = $db->getOne('SELECT name FROM table WHERE id = ?i',$_GET['id']);
 * $data = $db->getInd('id','SELECT * FROM ?n WHERE id IN ?a','table', array(1,2));
 * $data = $db->getAll("SELECT * FROM ?n WHERE mod=?s LIMIT ?i",$table,$mod,$limit);
 *
 * $ids  = $db->getCol("SELECT id FROM tags WHERE tagname = ?s",$tag);
 * $data = $db->getAll("SELECT * FROM table WHERE category IN (?a)",$ids);
 * 
 * $data = array('offers_in' => $in, 'offers_out' => $out);
 * $sql  = "INSERT INTO stats SET pid=?i,dt=CURDATE(),?u ON DUPLICATE KEY UPDATE ?u";
 * $db->query($sql,$pid,$data,$data);
 * 
 * if ($var === NULL) {
 *     $sqlpart = "field is NULL";
 * } else {
 *     $sqlpart = $db->parse("field = ?s", $var);
 * }
 * $data = $db->getAll("SELECT * FROM table WHERE ?p", $bar, $sqlpart);
 * 
 */

class SafeMySQL
{

	protected $conn;
	protected $stats;
	protected $emode;
	protected $exname;

	protected $defaults = array(
		'host'      => 'localhost',
		'user'      => 'root',
		'pass'      => '',
		'db'        => 'test',
		'port'      => NULL,
		'socket'    => NULL,
		'pconnect'  => FALSE,
		'charset'   => 'utf8',
		'errmode'   => 'exception',
		'exception' => 'Exception',
	);

	const RESULT_ASSOC = MYSQLI_ASSOC;
	const RESULT_NUM   = MYSQLI_NUM;

	function __construct($opt = array())
	{
		$opt = array_merge($this->defaults,$opt);

		$this->emode  = $opt['errmode'];
		$this->exname = $opt['exception'];

		if (isset($opt['mysqli']))
		{
			if ($opt['mysqli'] instanceof mysqli)
			{
				$this->conn = $opt['mysqli'];
				return;

			} else {

				$this->error("Ошибка mysqli options");
			}
		}

		if ($opt['pconnect'])
		{
			$opt['host'] = "p:".$opt['host'];
		}

		@$this->conn = mysqli_connect($opt['host'], $opt['user'], $opt['pass'], $opt['db'], $opt['port'], $opt['socket']);
		if ( !$this->conn )
		{
			$this->error(mysqli_connect_errno()." ".mysqli_connect_error());
		}

		mysqli_set_charset($this->conn, $opt['charset']) or $this->error(mysqli_error($this->conn));
		unset($opt);
	}

	public function query()
	{	
		return $this->rawQuery($this->prepareQuery(func_get_args()));
	}

	public function fetch($result,$mode=self::RESULT_ASSOC)
	{
		return @mysqli_fetch_array($result, $mode);
	}

	public function affectedRows()
	{
		return mysqli_affected_rows ($this->conn);
	}

	public function insertId()
	{
		return mysqli_insert_id($this->conn);
	}

	public function numRows($result)
	{
		return mysqli_num_rows($result);
	}
	public function free($result)
	{
		mysqli_free_result($result);
	}
	public function getOne()
	{
		$query = $this->prepareQuery(func_get_args());
		if ($res = $this->rawQuery($query))
		{
			$row = $this->fetch($res);
			if (is_array($row)) {
				return reset($row);
			}
			$this->free($res);
		}
		return FALSE;
	}
	public function getRow()
	{
		$query = $this->prepareQuery(func_get_args());
		if ($res = $this->rawQuery($query)) {
			$ret = $this->fetch($res);
			$this->free($res);
			return $ret;
		}
		return FALSE;
	}
	public function getCol()
	{
		$ret   = array();
		$query = $this->prepareQuery(func_get_args());
		if ( $res = $this->rawQuery($query) )
		{
			while($row = $this->fetch($res))
			{
				$ret[] = reset($row);
			}
			$this->free($res);
		}
		return $ret;
	}
	public function getAll()
	{
		$ret   = array();
		$query = $this->prepareQuery(func_get_args());
		if ( $res = $this->rawQuery($query) )
		{
			while($row = $this->fetch($res))
			{
				$ret[] = $row;
			}
			$this->free($res);
		}
		return $ret;
	}
	public function getInd()
	{
		$args  = func_get_args();
		$index = array_shift($args);
		$query = $this->prepareQuery($args);

		$ret = array();
		if ( $res = $this->rawQuery($query) )
		{
			while($row = $this->fetch($res))
			{
				$ret[$row[$index]] = $row;
			}
			$this->free($res);
		}
		return $ret;
	}
	public function getIndCol()
	{
		$args  = func_get_args();
		$index = array_shift($args);
		$query = $this->prepareQuery($args);

		$ret = array();
		if ( $res = $this->rawQuery($query) )
		{
			while($row = $this->fetch($res))
			{
				$key = $row[$index];
				unset($row[$index]);
				$ret[$key] = reset($row);
			}
			$this->free($res);
		}
		return $ret;
	}
	public function parse()
	{
		return $this->prepareQuery(func_get_args());
	}
	public function whiteList($input,$allowed,$default=FALSE)
	{
		$found = array_search($input,$allowed);
		return ($found === FALSE) ? $default : $allowed[$found];
	}
	public function filterArray($input,$allowed)
	{
		foreach(array_keys($input) as $key )
		{
			if ( !in_array($key,$allowed) )
			{
				unset($input[$key]);
			}
		}
		return $input;
	}
	public function lastQuery()
	{
		$last = end($this->stats);
		return $last['query'];
	}
	public function getStats()
	{
		return $this->stats;
	}
	protected function rawQuery($query)
	{
		$start = microtime(TRUE);
		$res   = mysqli_query($this->conn, $query);
		$timer = microtime(TRUE) - $start;

		$this->stats[] = array(
			'query' => $query,
			'start' => $start,
			'timer' => $timer,
		);
		if (!$res)
		{
			$error = mysqli_error($this->conn);
			
			end($this->stats);
			$key = key($this->stats);
			$this->stats[$key]['error'] = $error;
			$this->cutStats();
			
			$this->error("$error. Полный запрос: [$query]");
		}
		$this->cutStats();
		return $res;
	}
	protected function prepareQuery($args)
	{
		$query = '';
		$raw   = array_shift($args);
		$array = preg_split('~(\?[nsiuap])~u',$raw, '', PREG_SPLIT_DELIM_CAPTURE);
		$anum  = count($args);
		$pnum  = floor(count($array) / 2);
		if ( $pnum != $anum )
		{
			$this->error("Количество аргументов $anum не совпадает с количеством холдеров ($pnum) в [$raw]");
		}

		foreach ($array as $i => $part)
		{
			if ( ($i % 2) == 0 )
			{
				$query .= $part;
				continue;
			}

			$value = array_shift($args);
			switch ($part)
			{
				case '?n':
					$part = $this->escapeIdent($value);
					break;
				case '?s':
					$part = $this->escapeString($value);
					break;
				case '?i':
					$part = $this->escapeInt($value);
					break;
				case '?a':
					$part = $this->createIN($value);
					break;
				case '?u':
					$part = $this->createSET($value);
					break;
				case '?p':
					$part = $value;
					break;
			}
			$query .= $part;
		}
		return $query;
	}

	protected function escapeInt($value)
	{
		if ($value === NULL)
		{
			return 'NULL';
		}
		if(!is_numeric($value))
		{
			$this->error("?i должен быть integer ".gettype($value)." given");
			return FALSE;
		}
		if (is_float($value))
		{
			$value = number_format($value, 0, '.', '');
		} 
		return $value;
	}

	protected function escapeString($value)
	{
		if ($value === NULL)
		{
			return 'NULL';
		}
		return	"'".mysqli_real_escape_string($this->conn,$value)."'";
	}

	protected function escapeIdent($value)
	{
		if ($value)
		{
			return "`".str_replace("`","``",$value)."`";
		} else {
			$this->error("Пустое значение ?n");
		}
	}

	protected function createIN($data)
	{
		if (!is_array($data))
		{
			$this->error("Значение ?a должно быть массивом");
			return;
		}
		if (!$data)
		{
			return 'NULL';
		}
		$query = $comma = '';
		foreach ($data as $value)
		{
			$query .= $comma.$this->escapeString($value);
			$comma  = ",";
		}
		return $query;
	}

	protected function createSET($data)
	{
		if (!is_array($data))
		{
			$this->error("?u должен быть массивом. ".gettype($data));
			return;
		}
		if (!$data)
		{
			$this->error("Массив для ?u пуст");
			return;
		}
		$query = $comma = '';
		foreach ($data as $key => $value)
		{
			$query .= $comma.$this->escapeIdent($key).'='.$this->escapeString($value);
			$comma  = ",";
		}
		return $query;
	}

	protected function error($err)
	{
		$err  = __CLASS__.": ".$err;

		if ( $this->emode == 'error' )
		{
			$err .= ". Error initiated in ".$this->caller().", thrown";
			trigger_error($err,E_USER_ERROR);
		} else {
			throw new $this->exname($err);
		}
	}

	protected function caller()
	{
		$trace  = debug_backtrace();
		$caller = '';
		foreach ($trace as $t)
		{
			if ( isset($t['class']) && $t['class'] == __CLASS__ )
			{
				$caller = $t['file']." on line ".$t['line'];
			} else {
				break;
			}
		}
		return $caller;
	}
	protected function cutStats()
	{
		if ( count($this->stats) > 100 )
		{
			reset($this->stats);
			$first = key($this->stats);
			unset($this->stats[$first]);
		}
	}
}
?>