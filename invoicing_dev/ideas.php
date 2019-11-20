<?php
header('Content-Type: text/plain');

function generate_where_clause($start, $end) {
  $wheres = array();

  if (!empty($start)) {
    $wheres[] = "`date` >= '$start'";
  }

  if (!empty($end)) {
    $wheres[] = "`date` <= '$end'";
  }

  return implode(" AND ", $wheres);
}

$start = ""; $end = "";
echo "WHERE " . generate_where_clause($start,$end) . ";\n";

$start = "2018-01-01"; $end = "";
echo "WHERE " . generate_where_clause($start,$end) . ";\n";

$start = "2018-01-01"; $end = "2018-02-01";
echo "WHERE " . generate_where_clause($start,$end) . ";\n";

$start = ""; $end = "2018-02-01";
echo "WHERE " . generate_where_clause($start,$end) . ";\n";



$start = ""; $end = "";
echo "WHERE 1"  . (!empty($start) ? " AND date >= '$start'" : "") . (!empty($end) ? " AND date <= '$end'" : "") . ";\n";

$start = "2018-01-01"; $end = "";
echo "WHERE 1"  . (!empty($start) ? " AND date >= '$start'" : "") . (!empty($end) ? " AND date <= '$end'" : "") . ";\n";

$start = "2018-01-01"; $end = "2018-02-01";
echo "WHERE 1" . (!empty($start) ? " AND date >= '$start'" : "") . (!empty($end) ? " AND date <= '$end'" : "") . ";\n";

$start = ""; $end = "2018-02-01";
echo "WHERE 1" . (!empty($start) ? " AND date >= '$start'" : "") . (!empty($end) ? " AND date <= '$end'" : "") . ";\n";

?>