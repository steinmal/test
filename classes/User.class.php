<?php
    class User {
        private $brukernavn;
        private $passord;
        
        function __construct($brukernavn,$passord){
            $this->brukernavn = $brukernavn;
            $this->passord = $passord;
        }
        public function setPassword($newPassword) {
			$this->password = password_hash($newPassword, PASSWORD_DEFAULT);
			return $this;
		}
        public static function login($db, $brukernavn, $passord) {
            $stmt = $db->prepare("SELECT bruker_epost, bruker_passord FROM bruker WHERE bruker_epost=:email");
            $stmt->bindparam(':email', $brukernavn, PDO::PARAM_STR);
            $stmt->execute();
            
            if($rad = $stmt->fetch()) {
                $email = $rad['bruker_epost'];
                $hash = $rad['bruker_passord'];

                
            }
            if(password_verify($passord, $hash)) {
                    $_SESSION['innlogget'] = true;
                    $_SESSION['bruker'] = new User($email, $passord);
                    return true;
                }
            
            echo "Feil passord";
            return false;
        }
    }