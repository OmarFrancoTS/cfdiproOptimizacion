<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once('lib_mpdf/pdf/mpdf.php');
require_once('cnx_cfdi2.php');

if (!isset($_GET['prefijo']) || empty($_GET['prefijo'])) {
    die("Falta el prefijo de la BD");
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Falta el ID del Gasto");
}
if (!isset($_GET['tipo']) || empty($_GET['tipo'])) {
    die("Falta el tipo de Formato del Gasto");
}

$prefijobd = mysqli_real_escape_string($cnx_cfdi2, $_GET["prefijodb"]);
$idFolio   = (int) $_GET["id"];
$tipo      = mysqli_real_escape_string($cnx_cfdi2, $_GET["tipo"]);

if (substr($prefijobd, -1) != '_') {
    $prefijobd .= '_';
}

$prefijo = rtrim($prefijobd, "_");

mysqli_select_db($cnx_cfdi2, $database_cfdi);
mysqli_query($cnx_cfdi2, "SET NAMES 'utf8'");

$Multi         = 0;
$emisorID      = 0;
$color         = '#A9A9A3';
$color_letra   = '#000000';
$ruta_logo_multi = '';
$coloresMulti  = '';


switch ($tipo) {
    case 'VC':
        $nombreDoc = 'Combustible';
        $mostrarLitros = 1;
        $mostrarEstacion = 1;
        break;
    case 'DO':
        $nombreDoc = 'Deposito';
        $mostrarLitros = 0;
        $mostrarEstacion = 0;
        break;
    case 'NC':
        $nombreDoc = 'Nota de Cargo';
        $mostrarLitros = 0;
        $mostrarEstacion = 0;
        break;
    
    default:
        $nombreDoc = 'Gasto';
        $mostrarLitros = 1;
        $mostrarEstacion = 1;
        break;
}


$resSQL00 = "SELECT * FROM {$prefijobd}systemsettings";
$runSQL00 = mysqli_query($cnx_cfdi2, $resSQL00);
if ($runSQL00) {
    while ($rowSQL00 = mysqli_fetch_array($runSQL00)) {
        if (isset($rowSQL00['MultiEmisor'])) {
            $Multi = (int)$rowSQL00['MultiEmisor'];
        }
    }
}


$sqlVC = "SELECT 
            g.Fecha,
            g.XFolio,
            g.OficinaGastos_RID,
            g.Concepto, 
            g.Importe,
            g.Tranferencia,
            g.LitrosCombustible,
            g.Documentador,
            r.RemisionOperador AS Ticket,
            r.XFolio AS FolioRem,
            r.uRemolqueB_RID AS IdRemolque2, 
            c.RazonSocial AS ClienteNombre,
            rt.Origen, 
            rt.Destino,
            u.Unidad,
            u.Marca,
            u.Modelo,
            u.Placas,
            o.Operador,
            e.Estacion,
            b.Banco,
            b.CuentaContable
        FROM {$prefijobd}gastosviajes AS g 
        INNER JOIN {$prefijobd}remisiones AS r ON g.Remision_RID = r.ID
        INNER JOIN {$prefijobd}clientes AS c ON r.CargoACliente_RID = c.ID
        INNER JOIN {$prefijobd}rutas AS rt ON r.Ruta_RID = rt.ID
        INNER JOIN {$prefijobd}unidades AS u ON g.Unidad_RID = u.ID
        INNER JOIN {$prefijobd}operadores AS o ON g.OperadorNombre_RID = o.ID
        LEFT JOIN {$prefijobd}estaciones AS e ON g.Estacion_RID = e.ID
        LEFT JOIN {$prefijobd}bancos AS b ON g.TransferenciaBanco_RID = b.ID
        WHERE g.ID = {$idFolio}
        LIMIT 1";

$resVC = mysqli_query($cnx_cfdi2, $sqlVC);

if (!$resVC || mysqli_num_rows($resVC) == 0) {
    die("No se encontró información del vale.");
}

while ($rowVC = mysqli_fetch_array($resVC)) {
    $operador      = (!empty($rowVC['Operador'])) ? $rowVC['Operador'] : '-';
    $fechaRaw      = (!empty($rowVC['Fecha'])) ? $rowVC['Fecha'] : '-';
    $fecha         = date("d/m/Y", strtotime($fechaRaw));
    $folio         = (!empty($rowVC['XFolio'])) ? $rowVC['XFolio'] : '-';
    $unidad        = (!empty($rowVC['Unidad'])) ? $rowVC['Unidad'] : '-';
    $idRem2        = (!empty($rowVC['IdRemolque2'])) ? $rowVC['IdRemolque2'] : '-';
    $modalidad     = (!empty($idRem2)) ? 'Full' : 'Sencillo';
    $marca         = (!empty($rowVC['Marca'])) ? $rowVC['Marca'] : '-';
    $modelo        = (!empty($rowVC['Modelo'])) ? $rowVC['Modelo'] : '-';
    $placas        = (!empty($rowVC['Placas'])) ? $rowVC['Placas'] : '-';
    $viaje         = (!empty($rowVC['FolioRem'])) ? $rowVC['FolioRem'] : '-';
    $ticket        = (!empty($rowVC['Ticket'])) ? $rowVC['Ticket'] : '-';
    $cliente       = (!empty($rowVC['ClienteNombre'])) ? $rowVC['ClienteNombre'] : '-';
    $origen        = (!empty($rowVC['Origen'])) ? $rowVC['Origen'] : '-';    
    $destino       = (!empty($rowVC['Destino'])) ? $rowVC['Destino'] : '-';    
    $concepto      = (!empty($rowVC['Concepto'])) ? $rowVC['Concepto'] : '-';
    $litros        = number_format((float)$rowVC['LitrosCombustible'], 4, '.', ',');
    $importe       = '$ '.number_format((float)$rowVC['Importe'], 2, '.', ',');
    $estacion      = (!empty($rowVC['Estacion'])) ? $rowVC['Estacion'] : '-';
    $documentador  = (!empty($rowVC['Documentador'])) ? $rowVC['Documentador'] : '-';
    $emisorFirma   = (!empty($rowVC['Operador'])) ? $rowVC['Operador'] : '-';
    $banco         = (!empty($rowVC['Banco'])) ? $rowVC['Banco'] : '-';
    $cuentaBanco   = (!empty($rowVC['CuentaContable'])) ? $rowVC['CuentaContable'] : '-';
    $oficinaGastos = (int)$rowVC['OficinaGastos_RID'];
    $transferencia = (!empty($rowVC['Tranferencia'])) ? $rowVC['Tranferencia'] : '-';

    
    $codigoBarra = trim($ticket . $folio);
}


if ($Multi == 1 && $oficinaGastos > 0) {
    $resSQL03 = "SELECT of.ID, em.ID AS emiID
                 FROM {$prefijobd}oficinas AS of
                 LEFT JOIN {$prefijobd}emisores AS em ON of.Emisor_RID = em.ID
                 WHERE of.ID = {$oficinaGastos}
                 LIMIT 1";
    $runSQL03 = mysqli_query($cnx_cfdi2, $resSQL03);

    if ($runSQL03) {
        while ($rowSQL03 = mysqli_fetch_array($runSQL03)) {
            $emisorID = (int)$rowSQL03['emiID'];
        }
    }
}


$RazonSocial    = '';
$Calle          = '';
$NumeroExterior = '';
$NumeroInterior = '';
$Colonia        = '';
$CodigoPostal   = '';
$Ciudad         = '';
$Estado         = '';
$Telefono       = '';
$RFC            = '';
$Pais           = '';
$Municipio      = '';

if ($Multi == 1 && $emisorID >= 1) {
    $resSQL04 = "SELECT * FROM {$prefijobd}emisores WHERE ID = {$emisorID} LIMIT 1";
    $runSQL04 = mysqli_query($cnx_cfdi2, $resSQL04);

    if ($runSQL04) {
        while ($rowSQL04 = mysqli_fetch_array($runSQL04)) {
            $RazonSocial     = $rowSQL04['RazonSocial'];
            $Calle           = $rowSQL04['Calle'];
            $NumeroExterior  = $rowSQL04['NumeroExterior'];
            $NumeroInterior  = $rowSQL04['NumeroInterior'];
            $Colonia         = $rowSQL04['Colonia'];
            $CodigoPostal    = $rowSQL04['CodigoPostal'];
            $Ciudad          = $rowSQL04['Ciudad'];
            $Estado          = $rowSQL04['Estado'];
            $Telefono        = $rowSQL04['Telefono'];
            $RFC             = $rowSQL04['RFC'];
            $Pais            = $rowSQL04['Pais'];
            $Municipio       = $rowSQL04['Municipio'];
            $ruta_logo_multi = $rowSQL04['RutaLogo'];
        }
    }
} else {
    $resSQL0 = "SELECT * FROM {$prefijobd}systemsettings LIMIT 1";
    $runSQL0 = mysqli_query($cnx_cfdi2, $resSQL0);

    if ($runSQL0) {
        while ($rowSQL0 = mysqli_fetch_array($runSQL0)) {
            $RazonSocial    = $rowSQL0['RazonSocial'];
            $Calle          = $rowSQL0['Calle'];
            $NumeroExterior = $rowSQL0['NumeroExterior'];
            $NumeroInterior = $rowSQL0['NumeroInterior'];
            $Colonia        = $rowSQL0['Colonia'];
            $CodigoPostal   = $rowSQL0['CodigoPostal'];
            $Ciudad         = $rowSQL0['Ciudad'];
            $Estado         = $rowSQL0['Estado'];
            $Telefono       = $rowSQL0['Telefono'];
            $RFC            = $rowSQL0['RFC'];
            $Pais           = $rowSQL0['Pais'];
            $Municipio      = $rowSQL0['Municipio'];
        }
    }
}


$rutalogo = '../cfdipro/imagenes/' . $prefijo . '.jpg';

if ($Multi == 1 && !empty($ruta_logo_multi)) {
    $rutalogo = $ruta_logo_multi;
}


$resSQL921 = "SELECT id2, VCHAR FROM {$prefijobd}parametro WHERE id2 = 921 LIMIT 1";
$runSQL921 = mysqli_query($cnx_cfdi2, $resSQL921);
if ($runSQL921) {
    while ($rowSQL921 = mysqli_fetch_array($runSQL921)) {
        if (!empty($rowSQL921['VCHAR'])) {
            $color = $rowSQL921['VCHAR'];
        }
    }
}

$resSQL922 = "SELECT id2, VCHAR FROM {$prefijobd}parametro WHERE id2 = 922 LIMIT 1";
$runSQL922 = mysqli_query($cnx_cfdi2, $resSQL922);
if ($runSQL922) {
    while ($rowSQL922 = mysqli_fetch_array($runSQL922)) {
        if (!empty($rowSQL922['VCHAR'])) {
            $color_letra = $rowSQL922['VCHAR'];
        }
    }
}

$estilo_fondo = 'background-color: '.$color.'; color: '.$color_letra.';';


$domicilioEmpresa = array();

if (!empty($Calle))          $domicilioEmpresa[] = $Calle;
if (!empty($NumeroExterior)) $domicilioEmpresa[] = 'No. '.$NumeroExterior;
if (!empty($NumeroInterior)) $domicilioEmpresa[] = 'Int. '.$NumeroInterior;
if (!empty($Colonia))        $domicilioEmpresa[] = 'Col. '.$Colonia;
if (!empty($Municipio))      $domicilioEmpresa[] = $Municipio;
if (!empty($Ciudad))         $domicilioEmpresa[] = $Ciudad;
if (!empty($Estado))         $domicilioEmpresa[] = $Estado;
if (!empty($CodigoPostal))   $domicilioEmpresa[] = 'CP '.$CodigoPostal;
if (!empty($Pais))           $domicilioEmpresa[] = $Pais;

$empresaDomicilio = implode(', ', $domicilioEmpresa);

/*
|--------------------------------------------------------------------------
| FUNCION BLOQUE
|--------------------------------------------------------------------------
*/
function bloqueCombustible($data) {
    $logoHtml = '';

    if (!empty($data['logo']) && @file_exists($data['logo'])) {
        $logoHtml = '<img src="'.$data['logo'].'" style="width:115px; max-height:58px;" />';
    }

    $html = '
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000;">
        <tr>
            <td width="20%" valign="top" align="left">'.$logoHtml.'</td>
            <td width="50%" valign="top" align="left" style="font-size: 9px; line-height: 1.3;">
                <div style="font-size: 12px; font-weight: bold; padding-bottom: 2px;">'.$data['empresaNombre'].'</div>
                <div>'.$data['empresaDomicilio'].'</div>
                <div><b>RFC:</b> '.$data['empresaRFC'].'</div>
                <div><b>Tel:</b> '.$data['empresaTel'].'</div>
            </td>
            <td width="30%" valign="top" align="center" style="font-size: 18px; font-weight: bold;">'.$data['nombreDoc'].'</td>
        </tr>
    </table>

    <br>

    <table width="100%" cellpadding="2" cellspacing="0" border="0" style="font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000;">
        <tr>
            <td width="58%" valign="top" align="center">
                <div style="font-weight: bold; font-size: 11px;">Operador</div>
                <div style="padding-top: 5px;">'.$data['operador'].'</div>
            </td>

            <td width="42%" valign="top">
                <table width="100%" cellpadding="2" cellspacing="0" border="0">
                    <tr>
                        <td width="50%" align="center" style="font-weight: bold;">Fecha</td>
                        <td width="50%" align="center" style="font-weight: bold;">Folio</td>
                    </tr>
                    <tr>
                        <td align="center">'.$data['fecha'].'</td>
                        <td align="center">
                            <div style="border: 1px solid #000; padding: 7px 0; font-weight: bold; font-size: 16px; color: #d32f2f;">
                                '.$data['folio'].'
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" align="center" style="padding-top: 4px;">
                            <barcode code="'.$data['codigoBarra'].'" type="C128A" size="0.85" height="1.0" />
                            <div style="font-size: 8px; padding-top: 2px;">'.$data['codigoBarra'].'</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <br>

    <table width="100%" cellpadding="4" cellspacing="0" border="0" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <tr>
            <td width="25%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Banco</td>
            <td width="25%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Cta Bancaria</td>
            <td width="25%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Fecha</td>
            <td width="25%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Folio</td>
        </tr>
        <tr>
            <td align="center">'.$data['banco'].'</td>
            <td align="center">'.$data['cuentaBanco'].'</td>
            <td align="center">'.$data['fecha'].'</td>
            <td align="center" style="font-weight:bold;">'.$data['folio'].'</td>
        </tr>
    </table>

    <br>

    <table width="100%" cellpadding="4" cellspacing="0" border="0" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <tr>
            <td width="20%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Unidad</td>
            <td width="20%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Modalidad</td>
            <td width="20%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Marca</td>
            <td width="20%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Modelo</td>
            <td width="20%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Placas</td>
        </tr>
        <tr>
            <td align="center">'.$data['unidad'].'</td>
            <td align="center">'.$data['modalidad'].'</td>
            <td align="center">'.$data['marca'].'</td>
            <td align="center">'.$data['modelo'].'</td>
            <td align="center">'.$data['placas'].'</td>
        </tr>
    </table>

    <br>

    <table width="100%" cellpadding="4" cellspacing="0" border="0" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <tr>
            <td width="15%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Viaje</td>
            <td width="30%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Cliente</td>
            <td width="25%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Origen</td>
            <td width="30%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Destino</td>
        </tr>
        <tr>
            <td align="center">'.$data['viaje'].'</td>
            <td align="center">'.$data['cliente'].'</td>
            <td align="center">'.$data['origen'].'</td>
            <td align="center">'.$data['destino'].'</td>
        </tr>
    </table>

    <br>';

 if (!empty($data['mostrarLitros'])) {
        $html .= '
        <table width="100%" cellpadding="4" cellspacing="0" border="0" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
            <tr>
                <td width="40%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Concepto</td>
                <td width="15%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Ltrs</td>
                <td width="20%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Transferencia</td>
                <td width="25%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Importe</td>
            </tr>
            <tr>
                <td align="center">'.$data['concepto'].'</td>
                <td align="center">'.$data['litros'].'</td>
                <td align="center">'.$data['transferencia'].'</td>
                <td align="right" style="font-weight:bold; font-size: 13px;">'.$data['importe'].'</td>
            </tr>
        </table>';
    } else {
        $html .= '
        <table width="100%" cellpadding="4" cellspacing="0" border="0" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
            <tr>
                <td width="55%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Concepto</td>
                <td width="20%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Transferencia</td>
                <td width="25%" align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Importe</td>
            </tr>
            <tr>
                <td align="center">'.$data['concepto'].'</td>
                <td align="center">'.$data['transferencia'].'</td>
                <td align="right" style="font-weight:bold; font-size: 13px;">'.$data['importe'].'</td>
            </tr>
        </table>';
    }

    if (!empty($data['mostrarEstacion'])) {
        $html .= '
        <table width="38%" cellpadding="4" cellspacing="0" border="0" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin-top: 8px;">
            <tr>
                <td align="center" style="'.$data['estiloHeader'].' font-weight:bold;">Estacion</td>
            </tr>
            <tr>
                <td align="center">'.$data['estacion'].'</td>
            </tr>
        </table>';
    }
    
    $html .= '
    <br><br><br>

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
        <tr>
            <td width="45%" align="center">
                <div style="border-top: 1px solid #000; width: 85%; margin: 0 auto 4px auto;"></div>
                <div>'.$data['documentador'].'</div>
                <div style="font-size: 8px; font-weight: bold; padding-top: 6px;">Documentador</div>
            </td>
            <td width="10%"></td>
            <td width="45%" align="center">
                <div style="border-top: 1px solid #000; width: 85%; margin: 0 auto 4px auto;"></div>
                <div>'.$data['emisorFirma'].'</div>
                <div style="font-size: 8px; font-weight: bold; padding-top: 6px;">Emisor</div>
            </td>
        </tr>
    </table>
    ';

    return $html;
}


$data = array(
    'logo'             => $rutalogo,
    'empresaNombre'    => $RazonSocial,
    'empresaDomicilio' => $empresaDomicilio,
    'empresaRFC'       => $RFC,
    'empresaTel'       => $Telefono,
    'nombreDoc'        => $nombreDoc,

    'operador'         => $operador,
    'fecha'            => $fecha,
    'folio'            => $folio,
    'codigoBarra'      => $codigoBarra,

    'banco'            => $banco,
    'cuentaBanco'      => $cuentaBanco,

    'unidad'           => $unidad,
    'modalidad'        => $modalidad,
    'marca'            => $marca,
    'modelo'           => $modelo,
    'placas'           => $placas,

    'viaje'            => $viaje,
    'cliente'          => $cliente,
    'origen'           => $origen,
    'destino'          => $destino,

    'concepto'         => $concepto,
    'litros'           => $litros,
    'transferencia'    => $transferencia,
    'importe'          => $importe,
    'estacion'         => $estacion,

    'documentador'     => $documentador,
    'emisorFirma'      => $emisorFirma,

    'estiloHeader'     => $estilo_fondo,
    'mostrarLitros'    => $mostrarLitros,
    'mostrarEstacion'  => $mostrarEstacion
);


$html = '
<html>
<head>
    <meta charset="utf-8" />
    <title>Vale de combustible</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000000;
        }
    </style>
</head>
<body>

    '.bloqueCombustible($data).'

    <div style="border-top: 1px solid #666666; margin: 16px 0 12px 0;"></div>

    '.bloqueCombustible($data).'

</body>
</html>
';

/*
|--------------------------------------------------------------------------
| PDF
|--------------------------------------------------------------------------
*/
$mpdf = new mPDF('utf-8', 'Letter', 0, '', 8, 8, 8, 8, 0, 0);
$mpdf->WriteHTML($html);
$nombreArchivo = strtolower(str_replace(' ', '_', $nombreDoc)).'_'.$folio.'.pdf';
$mpdf->Output($nombreArchivo, 'I');
exit;
?>