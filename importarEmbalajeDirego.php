<?php
require_once('cnx_cfdi2.php');
require_once __DIR__ . '/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

mysqli_select_db($cnx_cfdi2, $database_cfdi);
mysqli_set_charset($cnx_cfdi2, 'utf8');
$time = date('Y-m-d H:i:s');

$prefijobd = $_GET["prefijodb"];//trae prefijo
$idRem = $_GET["ID"];

function obtenerRangoIDs($cnx_cfdi2, $cantidad) {

    mysqli_begin_transaction($cnx_cfdi2);

    $result = mysqli_query($cnx_cfdi2, "SELECT MAX_ID FROM bas_idgen FOR UPDATE");

    if (!$result) {
        mysqli_rollback($cnx_cfdi2);
        return false;
    }

    $row = mysqli_fetch_row($result);
    $ultimoID = $row[0];

    $nuevoMax = $ultimoID + $cantidad;

    $update = mysqli_query($cnx_cfdi2, "UPDATE bas_idgen SET MAX_ID = $nuevoMax");

    if (!$update) {
        mysqli_rollback($cnx_cfdi2);
        return false;
    }

    mysqli_commit($cnx_cfdi2);

    // Devolver rango inicial
    return $ultimoID + 1;
}

function eliminarComillasDobles($value) {
    return str_replace('"', '', $value);
}

function limpiarNumero($numero) {
    return (float)preg_replace("/[^0-9\.]/", "", $numero);
}

if (isset($_POST['submit'])) {
    $inicio = microtime(true);
    if ($xlsx = SimpleXLSX::parse($_FILES['file']['tmp_name'])) {

        mysqli_begin_transaction($cnx_cfdi2);

        $filas = $xlsx->rows();

        $valoresInsert = [];
        $bloque = 500;

        $clavesUnidad = [];
        $clavesProducto = [];

        $resUnidad = mysqli_query($cnx_cfdi2, "SELECT ID, ClaveUnidad FROM {$prefijobd}c_ClaveUnidadPeso");
        while ($row = mysqli_fetch_assoc($resUnidad)) {
            $clavesUnidad[$row['ClaveUnidad']] = $row['ID'];
        }

        $resProducto = mysqli_query($cnx_cfdi2, "SELECT ID, ClaveProducto FROM {$prefijobd}c_ClaveProdServCP");
        while ($row = mysqli_fetch_assoc($resProducto)) {
            $clavesProducto[$row['ClaveProducto']] = $row['ID'];
        }

        $totalRegistros = count($filas) - 1; // quitar encabezado

        $idInicial = obtenerRangoIDs($cnx_cfdi2, $totalRegistros);

        if (!$idInicial) {
            die("Error generando IDs");
        }

        $idActual = $idInicial;

        foreach ($filas as $index => $fila) {
            $newid = $idActual;
            $idActual++;

            if ($index == 0) continue;

            $producto       = eliminarComillasDobles($fila[0]);
            $claveProducto  = $fila[1];
            $claveUnidad    = $fila[2];
            $cantidad       = limpiarNumero($fila[3]);
            $peso           = limpiarNumero($fila[4]);

            $idClaveUnidad   = $clavesUnidad[$claveUnidad] ? $clavesUnidad[$claveUnidad] : 'NULL';
            $idClaveProducto = $clavesProducto[$claveProducto] ? $clavesProducto[$claveProducto] : 'NULL';

            $valoresInsert[] = "(
                $newid,
                'Remisiones',
                '$idRem',
                '$cantidad',
                'PIEZAS',
                '$peso',
                'c_ClaveUnidadPeso',
                $idClaveUnidad,
                'c_ClaveProdServCP',
                $idClaveProducto,
                '".mysqli_real_escape_string($cnx_cfdi2, $producto)."'
            )";

            if (count($valoresInsert) == $bloque) {

                $sql = "INSERT INTO {$prefijobd}remisionessub
                (ID,FolioSub_REN,FolioSub_RID,Cantidad,Embalaje,peso,
                 ClaveUnidadPeso_REN,ClaveUnidadPeso_RID,
                 ClaveProdServCP_REN,ClaveProdServCP_RID,Descripcion)
                 VALUES " . implode(",", $valoresInsert);

                mysqli_query($cnx_cfdi2, $sql);

                $valoresInsert = [];
            }
        }

        if (!empty($valoresInsert)) {

            $sql = "INSERT INTO {$prefijobd}remisionessub
            (ID,FolioSub_REN,FolioSub_RID,Cantidad,Embalaje,peso,
             ClaveUnidadPeso_REN,ClaveUnidadPeso_RID,
             ClaveProdServCP_REN,ClaveProdServCP_RID,Descripcion)
             VALUES " . implode(",", $valoresInsert);

            mysqli_query($cnx_cfdi2, $sql);
        }

        mysqli_commit($cnx_cfdi2);

        echo "<script>alert('Importacion Exitosa');</script>";
        echo "<br><b>Tiempo total:</b> " . number_format($tiempoTotal, 4) . " segundos";

    } else {
        echo SimpleXLSX::parseError();
    }
}


?>

<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8">
    <title>Importar XML</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script>
    (function(){
      var k='ui-theme', s=null;
      try{s=localStorage.getItem(k);}catch(e){}
      if(s==='light'||s==='dark'){
        document.documentElement.setAttribute('data-theme',s);
      }else if(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches){
        document.documentElement.setAttribute('data-theme','dark');
      }else{
        document.documentElement.setAttribute('data-theme','light');
      }
    })();
    </script>

    <link rel="stylesheet" href="reportes_ui.css">
    <script src="reportes_ui.js"></script>

    <style>
      .ajax-box{
        position:relative;
      }
      .ajax-results{
        position:absolute;
        top:100%;
        left:0;
        right:0;
        z-index:30;
        margin-top:6px;
        background:var(--panel-strong, rgba(255,255,255,.95));
        border:var(--border);
        border-radius:16px;
        box-shadow:var(--shadow);
        overflow:hidden;
        max-height:260px;
        overflow-y:auto;
        display:none;
        backdrop-filter:blur(18px) saturate(1.2);
        -webkit-backdrop-filter:blur(18px) saturate(1.2);
      }
      .ajax-item{
        padding:10px 12px;
        cursor:pointer;
        border-top:var(--border-soft);
        font-weight:700;
        color:var(--text);
        background:var(--row-bg);
      }
      .ajax-item:first-child{
        border-top:none;
      }
      .ajax-item:hover{
        background:var(--row-hover);
      }
      .ajax-empty{
        padding:10px 12px;
        color:var(--text-soft);
        font-weight:700;
        background:var(--row-bg);
      }
      .picked-note{
        margin-top:6px;
        font-size:.88rem;
        color:var(--text-soft);
        font-weight:700;
      }
    </style>
</head>
<body>
    <div class="container-sm">
        <div class="header">
            <div>
                <h1>Importar Embalaje</h1>
            </div>

            <button id="themeToggle" class="btn-theme" type="button">
                <span class="sun">☀️</span><span class="moon" style="display:none;">🌙</span> Tema
            </button>
        </div>

        <div class="panel">

            <div class="panel-body">
                <form method="post" enctype="multipart/form-data" autocomplete="off">


                    <div class="field">
                        <label for="file">Selecciona el archivo Excel (XSLX):</label>
                        <input type="file" name="file" id="file"/>   
                    </div>

                    <div class="actions" style="margin-top:16px;justify-content:flex-end;">
                        <button type="submit" name="submit" value="1" class="btn primary">Importar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  if (window.ReportesUI && typeof ReportesUI.initThemeAuto === 'function') {
    ReportesUI.initThemeAuto('themeToggle');
  } else if (window.ReportesUI && typeof ReportesUI.initTheme === 'function') {
    ReportesUI.initTheme('themeToggle');
    if (window.ReportesUI && typeof ReportesUI.hideThemeInIframe === 'function') {
      ReportesUI.hideThemeInIframe('themeToggle');
    }
  }

  function debounce(fn, delay){
    var timer = null;
    return function(){
      var ctx = this, args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function(){
        fn.apply(ctx, args);
      }, delay || 250);
    };
  }

});
</script>
</body>
</html>
