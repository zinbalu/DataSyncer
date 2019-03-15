<?php
	$aDataToDisplay = DBC::$system->select("SELECT * FROM settings");
?>
<html>
<head>
	<script type="javascript" src="../js/<?=str_replace('.php', '.js', __FILE__)?>"></script>
</head>
<body>
	<h1>dump stuff</h1>
	<table class="services width100">
		<thead>
		<?php
			foreach(array_keys(reset($aDataToDisplay)) as $sHeader) echo "<th>$sHeader</th>\n";
		?>
		</thead>
		<tbody>
		<?php
			foreach($aDataToDisplay as $aRowData){
				echo "<tr>\n";
				foreach($aRowData as $mValue) echo "<td>$mValue</td>\n";
				echo "</tr>\n";
			}
		?>
		</tbody>
	</table>
</body>
</html>
