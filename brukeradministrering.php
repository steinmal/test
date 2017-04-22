<?php
spl_autoload_register(function ($class_name) {
    require_once 'classes/' . $class_name . '.class.php';
});

require_once 'vendor/autoload.php';
include('auth.php');
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);
$BrukerReg = new BrukerRegister($db);
$TeamReg = new TeamRegister($db);
$error = "";
$visNye = "";

session_start();

if(!isset($_SESSION['innlogget']) || $_SESSION['innlogget'] == false){
    header("Location: index.php?error=ikkeInnlogget");
    return;
}

if(!isset($_SESSION['brukerTilgang']) || $_SESSION['brukerTilgang']->isBrukeradmin() != true){
    header("Location: index.php?error=manglendeRettighet&side=bradm");
    return;
}
if(isset($_GET['action']) && $_GET['action'] == "aktiver"){
    if(!isset($_GET['brukerId'])){
        $error = "noSelection";
    } else {
        if($BrukerReg->hentBruker($_GET['brukerId'])->isAktivert()){
            $error = "erAktivert";
        } else {
            $BrukerReg->aktiverBruker($_GET['brukerId']);
            $error = "aktivert";
        }
    }
} else {
    if(isset($_REQUEST['error'])){
        $error = $_REQUEST['error'];
    }

}
if(isset($_GET['visNye'])){
    $visNye = 'on';
}

$brukere = $BrukerReg->hentAlleBrukere();
$venterGodkjenning = 0;
foreach($brukere as $bruker){
    if(!$bruker->isAktivert()){
        $venterGodkjenning++;
    }
}

echo $twig->render('brukeradministrering.html', array('innlogget'=>$_SESSION['innlogget'], 'error'=>$error, 'venterGodkjenning'=>$venterGodkjenning, 'visNye'=>$visNye, 'bruker'=>$_SESSION['bruker'],'TeamReg'=>$TeamReg, 'brukerReg'=>$BrukerReg, 'brukere'=>$brukere, 'brukerTilgang'=>$_SESSION['brukerTilgang']));

?>