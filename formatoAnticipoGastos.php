<?php
ini_set('memory_limit', '2048M');
ini_set('default_charset', 'utf-8');
set_time_limit(2000);
ini_set('max_execution_time', 2000);

require_once __DIR__ . '/vendor/autoload.php'; // mPDF 6.1 (composer)
require_once('cnx_cfdi3.php');

if (!isset($_GET['prefijodb']) || $_GET['prefijodb'] === '') die("Falta prefijodb");
if (!isset($_GET['id']) || $_GET['id'] === '') die("Falta id");

$prefijobd = $_GET['prefijodb'];
$id        = (int)$_GET['id'];
if ($id <= 0) die("ID inválido");

// Conexión (tu cnx_cfdi3.php debe dejar $cnx_cfdi3 como mysqli)
if (!isset($cnx_cfdi3) || !$cnx_cfdi3) die("No hay conexión mysqli en cnx_cfdi3.php");
if ($cnx_cfdi3->connect_error) die('Error de conexión a la base de datos.');
mysqli_set_charset($cnx_cfdi3, "utf8");

// Sanitiza prefijo (evita inyección por nombre de tabla)
$prefijobd = preg_replace('/[^a-zA-Z0-9_]/', '', $prefijobd);
if ($prefijobd === '') die("Prefijo inválido.");
if (strpos($prefijobd, "_") === false) $prefijobd .= "_";

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// =========================
// 1) Razon social (RECIBÍ DE)
// =========================
$recibi_de = '';
$sqlSS = "SELECT RazonSocial FROM {$prefijobd}systemsettings LIMIT 1";
$stmtSS = $cnx_cfdi3->prepare($sqlSS);
if (!$stmtSS) die("Error prepare systemsettings: ".$cnx_cfdi3->error);
$stmtSS->execute();
$stmtSS->bind_result($recibi_de);
$stmtSS->fetch();
$stmtSS->close();

if ($recibi_de === '') $recibi_de = 'CENTROS DE DISTRIBUCION, S A DE C V';

// =========================
// 2) Datos de gastosviajes
// =========================
$folio           = '';
$fecha_salida    = '';
$cantidad_letra  = '';
$cantidad_num    = '';
$nombre_operador = '';
$unidad          = '';
$remolque        = '';

$sqlGA = "SELECT a.XFolio,
                 a.Fecha,
                 a.Concepto,
                 a.Importe,
                 IFNULL(b.Operador,'') AS Operador,
                 IFNULL(c.Unidad,'')   AS Unidad,
                 IFNULL(d.Unidad,'')   AS Remolque
          FROM {$prefijobd}gastosviajes a
          LEFT JOIN {$prefijobd}operadores b ON a.OperadorNombre_RID = b.ID
          LEFT JOIN {$prefijobd}unidades   c ON a.Unidad_RID   = c.ID
          INNER JOIN {$prefijobd}remisiones AS r ON a.Remision_RID = r.ID
          LEFT JOIN {$prefijobd}unidades   d ON r.uRemolqueA_RID = d.ID
          WHERE a.ID = ?
          LIMIT 1";

$stmtGA = $cnx_cfdi3->prepare($sqlGA);
if (!$stmtGA) die("Error prepare gastosviajes: ".$cnx_cfdi3->error);

$stmtGA->bind_param('i', $id);
$stmtGA->execute();
$stmtGA->bind_result($folio, $fecha_salida, $cantidad_letra, $cantidad_num, $nombre_operador, $unidad, $remolque);
$stmtGA->fetch();
$stmtGA->close();

if ($folio === '') die("No encontré el gasto de viaje con ese ID.");

// Ajustes finales
$fecha_salida = ($fecha_salida !== '') ? date('d/m/Y', strtotime($fecha_salida)) : date('d/m/Y');
$fecha_regreso = ''; // si luego lo agregas en BD, aquí lo pones
$cantidad_num = ($cantidad_num !== '') ? number_format((float)$cantidad_num, 2, '.', ',') : '0.00';

if ($cantidad_letra === '') $cantidad_letra = 'ANTICIPO PARA GASTOS DE VIAJE';
if ($nombre_operador === '') $nombre_operador = '__________';
if ($unidad === '') $unidad = '_____';
if ($remolque === '') $remolque = '_____';

// Texto fijo
$texto_carta =
"obligándome de reintegrar dicho importe por cuenta de gastos, en efectivo o por la combinación
de ambos dentro de los 8 días siguientes a la fecha estimada de regreso, en la inteligencia de
no hacerlo así, les autorizo a que me sea descontado de mi sueldo en el pago quincenal o comisión que
corresponda.";

// =========================
// mPDF CARTA (Letter) - mPDF 6.1 (constructor viejo PHP 5.5)
// =========================
$mpdf = new mPDF('utf-8', 'letter', 0, '', 10, 10, 10, 10, 0, 0);
$mpdf->SetAutoPageBreak(true, 10);
$mpdf->SetDisplayMode('fullpage');
$mpdf->SetTitle('Solicitud de Anticipo');
$mpdf->SetAuthor('TractoSoft');
$mpdf->SetFont('helvetica');

function renderCopia($folio, $fecha_salida, $fecha_regreso, $recibi_de, $cantidad_letra, $cantidad_num, $texto_carta, $nombre_operador, $unidad, $remolque){
    $folio = h($folio);
    $fecha_salida = h($fecha_salida);
    $fecha_regreso = h($fecha_regreso);
    $recibi_de = h($recibi_de);
    $cantidad_letra = h($cantidad_letra);
    $cantidad_num = h($cantidad_num);
    $texto_carta = nl2br(h($texto_carta));
    $nombre_operador = h($nombre_operador);
    $unidad = h($unidad);
    $remolque = h($remolque);

    $alto_copia_mm = 125;

    return '
    <div class="copia" style="height: '.$alto_copia_mm.'mm;">
        <div class="tituloRow">
            <div class="titulo">SOLICITUD DE ANTICIPO PARA GASTOS DE VIAJE</div>
            <div class="folioBox">'.$folio.'</div>
        </div>

        <div class="linea"></div>

        <table class="tblMeta" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td style="width: 55%;">
                    <span class="lbl">Fecha estimada de salida:</span>
                    <span class="val under">'.$fecha_salida.'</span>
                    <span class="lbl">; de regreso</span>
                    <span class="val under" style="min-width: 28mm; display:inline-block;">'.$fecha_regreso.'</span>
                </td>
            </tr>
            <tr>
                <td style="width: 45%; text-align:right;">
                    <span class="lbl">Recibí de</span><br>
                    <span class="val under" style="min-width: 70mm; display:inline-block; text-align:center;">'.$recibi_de.' la cantidad de $'.$cantidad_num.'</span>
                </td>
            </tr>
        </table>

        <table class="tblMonto" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td style="width:70%;">
                    <span class="lbl">por concepto de anticipo para gastos de viaje a la ciudad o ciudades de:</span>
                </td>
                <td style="width:30%; text-align:center;">
                    
                    <span class="val under" style="min-width: 38mm; display:inline-block; text-align:center;"><b>'.$cantidad_letra.'</b></span>
                </td>
            </tr>
        </table>

       

        <div class="texto">'.$texto_carta.'</div>

        <div class="firmas">
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td style="width: 45%;">
                        <div class="firmaLine"></div>
                        <div class="firmaLbl">Vo. Bo. GERENCIA</div>
                    </td>
                    <td style="width: 10%;"></td>
                    <td style="width: 45%; text-align:center;">
                        <div class="acepto">ACEPTO</div>
                        <div class="firmante">'.$nombre_operador.'</div>
                    </td>
                </tr>
            </table>

            <table class="tblDatos" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td style="width: 40%;"><span class="lbl">DEPARTAMENTO:</span> <span class="underSmall"></span></td>
                    <td style="width: 30%;"><span class="lbl">UNIDAD:</span> <b>'.$unidad.'</b></td>
                    <td style="width: 30%;"><span class="lbl">REMOLQUE:</span> <b>'.$remolque.'</b></td>
                </tr>
            </table>
        </div>
    </div>';
}

ob_start();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 10mm; }
    html, body { margin:0; padding:0; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color:#000; }

    .sheet { width: 100%; }
    .copia { width: 100%; box-sizing: border-box; }

    .tituloRow { width:100%; position: relative; }
    .titulo {
        text-align:center;
        font-weight: 800;
        letter-spacing: 0.4px;
        font-size: 12.5pt;
        padding-top: 1mm;
        padding-bottom: 2mm;
    }
    .folioBox{
        position:absolute;
        right:0;
        top:0;
        border: 1px solid #333;
        padding: 1.2mm 3mm;
        font-weight: 800;
        color: #c00;
        font-size: 10pt;
        background:#fff;
    }

    .linea { border-top: 1px solid #333; margin: 1.2mm 0; }

    .lbl { font-weight: 700; font-size: 9.2pt; }
    .val { font-size: 9.2pt; }

    .under {
        border-bottom: 1px solid #000;
        padding: 0 2mm;
        display:inline-block;
        line-height: 1.15;
    }

    .tblMeta td { padding: 0.8mm 0; vertical-align: middle; }
    .tblMonto td { padding: 0.8mm 0; vertical-align: middle; }

    .underBlock{
        border-bottom: 1px solid #000;
        padding: 1.2mm 0;
        margin-top: 1.2mm;
        font-size: 9.6pt;
    }

    .texto{
        margin-top: 2.2mm;
        font-size: 8.7pt;
        line-height: 1.22;
    }

    .firmas{ margin-top: 6mm; }
    .firmaLine{ border-top: 1px solid #000; margin-top: 8mm; }
    .firmaLbl{ text-align:center; font-size: 8.6pt; font-weight: 700; margin-top: 1.2mm; }
    .acepto{ font-weight: 800; font-size: 9.2pt; margin-top: 2mm; }
    .firmante{ font-weight: 800; font-size: 8.2pt; margin-top: 1mm; }

    .tblDatos { margin-top: 3mm; }
    .tblDatos td { font-size: 8.4pt; padding-top: 1mm; }
    .underSmall{
        display:inline-block;
        border-bottom: 1px solid #000;
        min-width: 45mm;
        height: 3.6mm;
        vertical-align: middle;
    }

    .cut{
        border-top: 1px dashed #777;
        margin: 4mm 0;
    }
</style>
</head>
<body>
<div class="sheet">

    <?php echo renderCopia($folio, $fecha_salida, $fecha_regreso, $recibi_de, $cantidad_letra, $cantidad_num, $texto_carta, $nombre_operador, $unidad, $remolque); ?>

    <div class="cut"></div>

    <?php echo renderCopia($folio, $fecha_salida, $fecha_regreso, $recibi_de, $cantidad_letra, $cantidad_num, $texto_carta, $nombre_operador, $unidad, $remolque); ?>

</div>
</body>
</html>
<?php
$html = ob_get_clean();
while (ob_get_level() > 0) { @ob_end_clean(); }

$mpdf->WriteHTML($html);
$mpdf->Output("Solicitud_Anticipo_{$folio}.pdf", "I");
exit;