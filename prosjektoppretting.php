<?php
spl_autoload_register(function ($class_name) {
    require_once 'classes/' . $class_name . '.class.php';
});

require_once 'tilgangsfunksjoner.php';
require_once 'vendor/autoload.php';
include('auth.php');
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);
$ProsjektReg = new ProsjektRegister($db);
$BrukerReg = new BrukerRegister($db);
$TeamReg = new TeamRegister($db);
$error = "none";
$aktivert = "";


session_start();


if(!isInnlogget()){
    header("Location: index.php?error=ikkeInnlogget");
    return;
}
$aktivert = isAktiv();
if(!isProsjektadmin() || !$aktivert){
    header("Location: index.php?error=manglendeRettighet&side=propp");
    return;
}
/*if(isset($_REQUEST['prosjektId'])){
    echo $_REQUEST['prosjektId'];
    var_dump($_REQUEST['prosjektItd']);
}*/

$prosjektliste = $ProsjektReg->hentAlleProsjekt($db);
$brukerliste = $BrukerReg->hentAlleBrukere();
$teamListe = $TeamReg->hentAlleTeam();
$brukParent = true;
$valgtProsjekt = new Prosjekt();
if(!isset($_REQUEST['action'])){
    header("Location: prosjektadministrering.php?error=ingenAction");
    //echo "Ingen Action";
    return;
}
$action =  $_REQUEST['action'];
if(isset($_POST['opprettProsjekt'])){
    $nyttProsjekt = new Prosjekt();
    
    $inputOk = true;
    foreach(array('prosjektNavn', 'prosjektLeder', 'team', 'startDato', 'sluttDato') as $field) {
        if(!isset($_POST[$field]) || strcmp($_POST[$field], "") == 0) {
            $inputOk = false;
            $error="ingenVerdi";
            $felt=$field; //Burde vise alle felt samtidig
            //header('Location: prosjektoppretting.php?error=ingenVerdi&felt=' . $field . '&action=' . $action);
            // REFACTOR: Ikke bruk header for reload, men sett $error og fiks if-else-logikk slik at skjemaet vises på nytt men med feilmelding
            break;
        }
    }
    
    $nyttProsjekt->setNavn($_POST['prosjektNavn']);
    $nyttProsjekt->setParent($_POST['foreldreProsjekt'] == null ? 1 : $_POST['foreldreProsjekt']);
    $nyttProsjekt->setLeder($_POST['prosjektLeder']);
    $nyttProsjekt->setProductOwner($_POST['productOwner']);
    $nyttProsjekt->setTeam($_POST['team']);
    $nyttProsjekt->setBeskrivelse($_POST['prosjektBeskrivelse']);
    
    $start = $_REQUEST['startDato'];
    $slutt = $_REQUEST['sluttDato'];
    if($nyttProsjekt->getParent() != 1){
        $parent = $ProsjektReg->hentProsjekt($nyttProsjekt->getParent());
        if ($parent != NULL) {  //grunnprosjekter har ikke parent, tok med denne for å ikke få fatal error i linje 68 ved opprettelse av grunnprosjekt
            if(DateHelper::dateCompare($parent->getStartDato(), $start) < 0){
                $inputOk = false;
                $error = "ugyldigStart";
            }
            if(DateHelper::dateCompare($slutt, $parent->getSluttDato()) < 0){
                $inputOk = false;
                $error = "ugyldigStopp";
            }
        }
    }
    if($start > $slutt){
        $inputOk = false;
        $error = "stoppEtterStart";
    }
    $nyttProsjekt->setStartDato($start);
    $nyttProsjekt->setSluttDato($slutt);
    
    if($inputOk){
        if(!isset($_POST['prosjektId'])){
            $ProsjektReg->lagProsjekt($nyttProsjekt);
            header("Location: prosjektadministrering.php?error=lagret");
            return;
        }
        else{
            $nyttProsjekt->setId($_POST['prosjektId']);
            $ProsjektReg->redigerProsjekt($nyttProsjekt);
            header("Location: prosjektadministrering.php?error=redigert");
            return;
        }
    } elseif (isset($_POST['prosjektId'])) {
        $nyttProsjekt->setId($_POST['prosjektId']);
    }
} else {
    switch($action){
        case 'Opprett grunnprosjekt':
            $brukParent = false;
            break;
        case 'Rediger':
            if(!isset($_REQUEST['prosjektId'])){
                header("Location: prosjektadministrering.php?error=noRadio");
                return;
            }
            $valgtProsjekt = $ProsjektReg->hentProsjekt($_GET['prosjektId']); //Noe lignende dette
            break;
        case 'Opprett underprosjekt':
            if(!isset($_REQUEST['prosjektId'])){
                header("Location: prosjektadministrering.php?error=noRadio");
                return;
            }
            $parent = $ProsjektReg->hentProsjekt($_REQUEST['prosjektId']);
            $valgtProsjekt->setParent($parent->getId());
            $valgtProsjekt->setStartDato($parent->getStartDato());
            $valgtProsjekt->setSluttDato($parent->getSluttDato());
            break;
        case 'Arkiver':
            if(!isset($_REQUEST['prosjektId'])){
                header("Location: prosjektadministrering.php?error=noRadio");
                return;
            }
            $prosjekt = $ProsjektReg->hentProsjekt($_REQUEST['prosjektId']);
            $oversikt = new ProsjektOversikt($prosjekt, $ProsjektReg, new FaseRegister($db), new OppgaveRegister($db), new TimeregistreringRegister($db));
            $ProsjektReg->arkiverProsjekt($prosjekt->getId());
            foreach($oversikt->getAlleUnderProsjekt(true) as $uProsjekt){
                if($uProsjekt->getStatus() == 0){
                    $ProsjektReg->arkiverProsjekt($uProsjekt->getId(), 2);
                }
            }
            $ProsjektReg->arkiverProsjekt($_GET['prosjektId']);
            header("Location: prosjektadministrering.php?error=$error");
            return;
        case 'Gjenopprett':
            if(!isset($_REQUEST['prosjektId'])){
                header("Location: prosjektadministrering.php?error=noRadio");
                return;
            }
            $prosjekt = $ProsjektReg->hentProsjekt($_REQUEST['prosjektId']);
            $prosjektTemp = clone $prosjekt;
            while($prosjektTemp->getParent() != 1){
                while($prosjektTemp->getStatus() != 1 && $prosjektTemp->getParent() != 1){
                    $prosjektTemp = $ProsjektReg->hentProsjekt($prosjektTemp->getParent());
                }
                $oversikt = new ProsjektOversikt($prosjektTemp, $ProsjektReg, new FaseRegister($db), new OppgaveRegister($db), new TimeregistreringRegister($db));
                $oversikt->gjennopprett($ProsjektReg);
                $prosjektTemp->setStatus(0);
                $prosjektTemp = $ProsjektReg->hentProsjekt($prosjektTemp->getParent());
            }
            if($prosjektTemp->getStatus() != 0){
                $oversikt = new ProsjektOversikt($prosjektTemp, $ProsjektReg, new FaseRegister($db), new OppgaveRegister($db), new TimeregistreringRegister($db));
                $oversikt->gjennopprett($ProsjektReg);
            }
            $error = "gjennopprettet";
            header("Location: prosjektadministrering.php?error=$error");
            return;
        default:
            break;
    }
}

if(isset($nyttProsjekt)){
    $valgtProsjekt = $nyttProsjekt;
}

if(isset($_GET['error'])) {
    $error = $_GET['error'];
}

$twigs = array('aktivert'=>$aktivert, 'error'=>$error, 'innlogget'=>$_SESSION['innlogget'], 'TeamReg'=>$TeamReg, 'action'=>$action, 'teamListe'=>$teamListe, 'bruker'=>$_SESSION['bruker'], 'brukParent'=>$brukParent, 'valgtProsjekt'=>$valgtProsjekt, 'prosjekter'=>$prosjektliste, 'brukere'=>$brukerliste, 'brukerTilgang'=>$_SESSION['brukerTilgang']);
if(isset($parent)){
    $twigs['parent'] = $parent;
}

echo $twig->render('prosjektoppretting.html', $twigs);
?>