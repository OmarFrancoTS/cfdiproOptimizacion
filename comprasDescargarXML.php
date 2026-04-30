<?php

require_once('cnx_cfdi2.php');
mysqli_select_db($cnx_cfdi2,$database_cfdi);

$rutaarchivo = "";
$prefijo = $_GET["prefijo"];

$id = $_GET['id'];

$resSQL5 = "SELECT XML_DOCDATA, XML_DOCTYPE FROM " . $prefijo . "ComprasXML 
WHERE ID = '" . $id . "'";
$runSQL5 = mysqli_query($cnx_cfdi2, $resSQL5);

$temp_files = [];
if (!$runSQL5) {
    $mensaje  = 'Consulta no válida [evidencias]: ' . mysqli_error($cnx_cfdi2) . "\n";
    //$mensaje .= 'Consulta completa: ' . $resSQL5;
    //die($mensaje);
}

    while ($rowSQL5 = mysqli_fetch_assoc($runSQL5)) {
        $doc = $rowSQL5['XML_DOCDATA'];
        $docName = $rowSQL5['XML_DOCTYPE'];
    
        if (!empty($doc)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $docName . '"');
            header('Content-Length: ' . strlen($doc));
    
            echo $doc;
            exit; // MUY importante para detener ejecución
        }
}