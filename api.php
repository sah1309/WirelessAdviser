<?php
include_once("db.php");


 $qj = new QueryJson($_GET, $db);
 $q = (isset($_GET['q'])) ? $_GET['q'] : null ;

//$q = ''; //$q should be a $_GET param
 $allowedQueries = array('in','ev', 'mapPaths','mapPoints');
 if(in_array($q, $allowedQueries)) {
 	$qj->query($q);
 }

/**
* 
*/
class QueryJson
{
	private $self;
	private $db;
	private $col;
	private $colType;

	
	function __construct($self, $db)
	{
		$this->self = $self;
		$this->db = $db;
	}

	/**
	* handles choosing the correct query to run
	*/
	public function query($value)
	{
		if($value==='in') {
			$this->inventoryQuery();
		} elseif ($value==='ev') {
			$this->eventQuery();
		} elseif ($value==='mapPoints') {
			$this->mapPointsQuery();
		} elseif ($value==='mapPaths') {
			$this->mapPathsQuery();
		}
	}

	private function inventoryQuery()
	{
		$aColumns = array('device_id', 'display_name', 'ip_address', 'device_type', 'device_state', 'latitude', 'longitude', 'azimuth', 'height');
		$aTypes = array('integer', 'text', 'inet', 'text', 'text', 'double precision', 'double precision', 'double precision', 'double precision');

		$sFilter = $this->Filter($aColumns, $aTypes);

		$query = "SELECT COUNT(device_id) FROM wirelessadviser.devices $sFilter";
		$length = $this->db->run($query);

		$sOrder = $this->orderBy($aColumns, $this->self['iSortCol_0'], $this->self['sSortDir_0'], $aTypes);

		$sLimit = $this->Limit();




		$query =  "SELECT device_id, display_name, ip_address, device_type, device_state, latitude, longitude, azimuth, height FROM wirelessadviser.devices $sFilter $sOrder $sLimit";
		$out = $this->db->run($query);

		echo $this->output($out, $length, "in");


	}

	public function eventQuery()
	{
		$aColumns = array('event_time', 'events.device_id', 'display_name', 'device_type', 'severity_id','description');
		$aTypes = array('text', 'integer', 'text', 'text', 'integer', 'text');

		$sFilter = $this->Filter($aColumns, $aTypes);

		$query = "SELECT count(events.event_time) FROM wirelessadviser.events LEFT OUTER JOIN wirelessadviser.devices ON events.device_id = devices.device_id $sFilter";
		$length = $this->db->run($query);

		$sOrder = $this->orderBy($aColumns, $this->self['iSortCol_0'], $this->self['sSortDir_0'], $aTypes);
		//$sOrder = "";
		$sLimit = $this->Limit();

		$query = "SELECT events.event_time, events.device_id, devices.display_name, devices.device_type, events.severity_id, events.description FROM wirelessadviser.events LEFT OUTER JOIN wirelessadviser.devices ON events.device_id = devices.device_id $sFilter $sOrder $sLimit;";
		$out = $this->db->run($query);

		echo $this->output($out, $length, "ev");

	}

	public function mapPointsQuery()
	{
		$query = "SELECT device_id,display_name,device_state,latitude::float,longitude::float FROM wirelessadviser.devices ORDER BY device_id;";
		$out = $this->db->run($query);
		

		echo json_encode($out);
	}

	public function mapPathsQuery()
	{
		$query = "SELECT d1.latitude AS p_lat,d1.longitude AS p_long,d2.latitude AS c_lat,d2.longitude AS c_long,d2.device_state AS status FROM wirelessadviser.links l
		INNER JOIN wirelessadviser.devices AS d1
		ON d1.device_id = l.parent_id
		INNER JOIN wirelessadviser.devices AS d2
		ON d2.device_id = l.child_id;";
		$out = $this->db->run($query);

		echo json_encode($out);
	}


	private function orderBy($aColumns, $sortCol, $sortDir, $aTypes)
	{
		$sOrder = "";
		if (isset($sortCol)) {
			$sOrder  = "ORDER BY ";
			switch ($aTypes[$sortCol]) {
				case 'integer':
				$sOrder .= $aColumns[$sortCol]."::integer";
				break;
				case 'inet':
				$sOrder .= "inet(".$aColumns[$sortCol].")";
				break;
				case 'double precision':
				$sOrder .= "cast(".$aColumns[$sortCol]." as double precision)";
				break;
				default:
				case 'text':
				$sOrder .= $aColumns[$sortCol];
				break;
			}
			$sOrder .= ($sortDir=='asc') ? ' asc' : ' desc' ;
		}
		return $sOrder;
	}

	private function Limit()
	{
		$sLimit = "";
		if ( isset( $this->self['iDisplayStart'] ) && $this->self['iDisplayLength'] != '-1' )
		{
			$sLimit = "LIMIT ".intval( $this->self['iDisplayLength'] )." OFFSET ".
			intval( $this->self['iDisplayStart'] );
		}
		return $sLimit;
	}


	/**
	 * Allows dataTables to filter data
	 */
	private function Filter($aColumns, $aTypes)
	{
		$sWhere = "";
		if ( isset($this->self['sSearch']) && $this->self['sSearch'] != "" ) {
			$sWhere = "WHERE (";
				for ( $i=0 ; $i<count($aColumns) ; $i++ ) {
					if($aColumns[$i] != "event_time" ) {
						if($aTypes[$i]==="text") {
							$sWhere .= $aColumns[$i]." LIKE '%".pg_escape_string( $this->self['sSearch'] )."%' OR ";
						}
					}
				}
				$sWhere = substr_replace( $sWhere, "", -3 );
				$sWhere .= ")";
}

return $sWhere;
}


private function output($out, $length, $type)
{
 				/*
 		* Output
 		*/
 		$output = array(
 			"sEcho" => intval($this->self['sEcho']),
 			"iTotalRecords" => $length[0]["count"],
 			"iTotalDisplayRecords" => $length[0]["count"],
 			"aaData" => array()
 			);


 		$row = array();
 		$line = array();
 		for ($i=0; $i < count($out); $i++) {
 			foreach ($out[$i] as $key => $value) {
			//$row[]= $out[$i][$key];
 				if($key==="device_id") {
 					$line[] = '<a href="device/'.$out[$i][$key].'" onclick="window.open(this.href, \'\', \'width=500,height=550\');return false;">'.$out[$i][$key].'</a>';
 				} elseif ($key==="ip_address") {
 					$line[] = '<a href="https://'.$out[$i][$key].'">'.$out[$i][$key]."</a>";
 				} else {
 					$line[] = $out[$i][$key];
 				}

 			}
 			if($type==="in") {
 				if($out[$i]["device_state"]=="up") {
 					$line["DT_RowClass"] = "success";
 				} else {
 					$line["DT_RowClass"] = "error";
 				}

 			} elseif ($type==="ev") {
 				switch ($out[$i]["severity_id"]) {
 					case '0':
 					$line["DT_RowClass"] = "success";
 					break;
 					case '1':
 					$line["DT_RowClass"] = "warning";
 					break;
 					case '2':
 					case '3':
 					case '4':
 					case '5':
 					$line["DT_RowClass"] = "error";
 					break;
 				}
 			}
 			$row[] = $line;
 			$line = null;
 		}

 		$output['aaData'] = $row;


 		echo json_encode( $output );
 	}


 }
 ?>
