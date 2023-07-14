<?php
    // | ENGINE | \\
    class BruteForceProtection
    {
        // | CONFIG | \\
        // Number of incorrectly entered passwords before ban (every time)
        const wrong_pass_inrow = 3; // for whitelist to
        // Amount of time after unban to delete the entry
        const time_for_remove = 216000; 
        // The duration of subsequent bans from the first to the last (which is repeated after the execution of the previous ones)
        const time_ban = array(600,3600,14400,86400);

        // Main code
        static $mysqli;
        static $intIP;
        static $userdata;
        static $ipdata;
        static $whitelistdata;
        static $isBan = false;
        public static function InsertDataBase($database)
        {
            self::$mysqli = $database;
        }
        public static function InitializeUser($userID,$hash,$inputIntIP)
        {
            self::$whitelistdata = null;
            $isBan = false;
            self::$intIP = $inputIntIP;
            if($inputIntIP != 0)
            {
                self::$ipdata = self::SelectIP($inputIntIP);
                if(self::$ipdata)
                {
                    if(self::$ipdata['bantime'] > time())
                    {
                        self::$isBan = true;
                    }
                }
            }

            if($userID > 0)
            {
                self::$userdata = self::SelectUser($userID,$hash);
                if(self::$userdata)
                {
                    if(self::$userdata['bantime'] > time())
                    {
                        if(self::$ipdata)
                        {
                            $changed = self::FixCurrentUser();
                            if($changed & 1 != 0) self::UpdateCurrentIP();
                            if($changed & 2 != 0) self::UpdateCurrentUser();
                        }
                        else
                        {
                            self::$ipdata = self::InsertIPLink(self::$intIP,self::$userdata['try'],self::$userdata['time'],self::$userdata['bantime']);
                        }
                        self::$isBan = true;
                    }
                }
            }
        }
        public static function CurrentUserWrongTry()
        {
            if(self::$userdata == null) self::$userdata = self::InsertUser();
            if(self::$ipdata == null) self::$ipdata = self::InsertIPLink(self::$intIP,0,time()+self::time_for_remove,0);
            
            self::FixCurrentUser();
            self::$ipdata['try']++;
            self::$userdata['try']++;
            if(self::$ipdata['try'] % self::wrong_pass_inrow == 0)
            {
                $i = (int)((self::$ipdata['try']/3) - 1);
                $timebancount = count(self::time_ban);
                if($i >= $timebancount)
                {
                    $i = $timebancount - 1;
                }
                self::$ipdata['bantime'] = self::$userdata['bantime'] = time()+self::time_ban[$i];
                self::$ipdata['time'] = self::$userdata['time'] = time()+self::time_for_remove;
                self::$isBan = true;
            }

            self::UpdateCurrentIP();
            self::UpdateCurrentUser();
        }
        public static function FixCurrentUser()
        {
            $changed = 0;
            if(self::$userdata['bantime'] > self::$ipdata['bantime'])
            {
                self::$ipdata['bantime'] = self::$userdata['bantime'];
                $changed = $changed | 1;
            }
            else if(self::$userdata['bantime'] < self::$ipdata['bantime'])
            {
                self::$userdata['bantime'] = self::$ipdata['bantime'];
                $changed = $changed | 2;
            }
            
            if(self::$userdata['try'] > self::$ipdata['try'])
            {
                self::$ipdata['try'] = self::$userdata['try'];
                $changed = $changed | 1;
            }
            else if(self::$userdata['try'] < self::$ipdata['try'])
            {
                self::$userdata['try'] = self::$ipdata['try'];
                $changed = $changed | 2;
            }
            
            if(self::$userdata['time'] > self::$ipdata['time'])
            {
                self::$ipdata['time'] = self::$userdata['time'];
                $changed = $changed | 1;
            }
            else if(self::$userdata['time'] < self::$ipdata['time'])
            {
                self::$userdata['time'] = self::$ipdata['time'];
                $changed = $changed | 2;
            }
            return $changed;
        }
        public static function UpdateCurrentUser()
        {
            $sql = "UPDATE `abf-users` SET time=".self::$userdata['time'].",bantime=".self::$userdata['bantime'].",try=".self::$userdata['try']." WHERE id=".self::$userdata['id'];
            self::$mysqli->query($sql);
        }
        public static function UpdateCurrentIP()
        {
            $sql = "UPDATE `abf-ip` SET time=".self::$ipdata['time'].",bantime=".self::$ipdata['bantime'].",try=".self::$ipdata['try']." WHERE id=".self::$ipdata['id'];
            self::$mysqli->query($sql);
        }
        public static function InsertUser()
        {
            $result = array("try" => 0,"time" => (time()+self::time_for_remove),"bantime" => 0);
            $result['hash'] = hash('sha256', $ip."|".time()."brut3f0rc3");
            $sql = "INSERT INTO `abf-users` (hash,try,time,bantime) VALUES ('".$result['hash']."',0,".time().",".$result['time'].")";
            if(self::$mysqli->query($sql) !== false) 
            {
                $result['id'] = self::$mysqli->insert_id;
                return $result;
            }
            echo "Error #1: " . self::$mysqli->error;
            exit;
        }
        public static function InsertIPLink($intIP,$try,$time,$bantime)
        {
            $result = array("ip" => $intIP,"try" => $try,"time" => $time,"bantime" => $bantime);
            $sql = "INSERT INTO `abf-ip` (ip,try,time,bantime) VALUES ($intIP,$try,$time,$bantime)";
            if(self::$mysqli->query($sql) !== false) 
            {
                $result['id'] = self::$mysqli->insert_id;
                return $result;
            }
            echo "Error #2: " . self::$mysqli->error;
            exit;
        }
        public static function SelectUser($userID,$hash)
        {
            $sql = "SELECT * FROM `abf-users` WHERE id=".(int)$userID." LIMIT 1";
            $result = self::$mysqli->query($sql);
            if($result !== false) // The request failed
            {
                if($data = $result->fetch_assoc())
                {
                    if(strcmp($data['hash'],$hash) !== 0) return null;
                    if($data['time'] < time())
                    {
                        self::DeleteUser($data['id']);
                        return null;
                    }
                    unset($data['hash']);
                    return $data;
                }
            }
            return null;
        }
        public static function DeleteUser($id)
        {
            $sql = "DELETE FROM `abf-users` WHERE id=$id";
            self::$mysqli->query($sql);
            return;
        }
        public static function SelectIP($intIP)
        {
            $sql = "SELECT * FROM `abf-ip` WHERE ip=".$intIP." LIMIT 1";
            $result = self::$mysqli->query($sql);
            if($result !== false) // The request failed
            {
                if($data = $result->fetch_assoc())
                {
                    if($data['time'] < time())
                    {
                        self::DeleteIP($data['id']);
                        return null;
                    }
                    return $data;
                }
            }
            return null;
        }
        public static function DeleteIP($id)
        {
            $sql = "DELETE FROM `abf-ip` WHERE id=$id";
            self::$mysqli->query($sql);
            return;
        }
        public static function WhiteListCheck($accountID)
        {
            $sql = "SELECT id,try FROM `abf-white` WHERE user=".self::$userdata['id']." AND account=".$accountID." LIMIT 1";
            $result = self::$mysqli->query($sql);
            if($result !== false) // The request failed
            {
                if($data = $result->fetch_assoc())
                {
                    if($data['try'] > 0)
                    {
                        self::$whitelistdata = $data;
                        return true;
                    }
                }
            }
            return false;
        }
        public static function WhiteListAdd($userID,$accountID)
        {
            $sql = "SELECT id,try FROM `abf-white` WHERE user=".$userID." AND account=".$accountID." LIMIT 1";
            $result = self::$mysqli->query($sql);
            if($result !== false) // The request failed
            {
                if($data = $result->fetch_assoc())
                {
                    $sql = "UPDATE `abf-white` SET try=".self::wrong_pass_inrow." WHERE id=".$whitelistID;
                    self::$mysqli->query($sql);
                    return;
                }
            }
            $sql = "INSERT INTO `abf-white` (user,account,try) VALUES ($userID,$accountID,".self::wrong_pass_inrow.")";
            if(self::$mysqli->query($sql) !== false) 
            {
                return;
            }
            echo "Error #3: " . self::$mysqli->error;
            exit;
        }
        public static function CurrentWhiteListRemoveTry()
        {
            if(self::$whitelistdata['try'] > 1)
            {
                self::$whitelistdata['try']--;
                $sql = "UPDATE `abf-white` SET try=".self::$whitelistdata['try']." WHERE id=".self::$whitelistdata['id'];
                self::$mysqli->query($sql);
            }
            else
            {
                self::WhiteListDelete(self::$whitelistdata['id']);
                self::$whitelistdata = null;
                self::$isBan = true;
            }
            return;
        }
        public static function WhiteListDelete($listID)
        {
            $sql = "DELETE FROM `abf-white` WHERE id=$listID";
            self::$mysqli->query($sql);
            return;
        }
        public static function ClearALL()
        {
            $sql = "DELETE FROM `abf-ip` WHERE time < ".time();
            self::$mysqli->query($sql);
            $sql = "DELETE FROM `abf-users` WHERE time < ".time();
            self::$mysqli->query($sql);
            $sql = "DELETE FROM `abf-white` WHERE time < ".time();
            self::$mysqli->query($sql);
            return;
        }
    }
    // | CUSTOM CODE | \\
    class AntiBruteForce
    {
        public static function InitializeBrowserUser()
        {
            // IP
            $client  = @$_SERVER['HTTP_CLIENT_IP'];
            $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
            $remote  = @$_SERVER['REMOTE_ADDR'];
            if(filter_var($client, FILTER_VALIDATE_IP)) $ip = $client;
            elseif(filter_var($forward, FILTER_VALIDATE_IP)) $ip = $forward;
            else $ip = $remote;
            $intIP = ip2long($ip);

            // UserID | Hash
            $userID = (int)$_COOKIE["bfid"];
            $hash = $_COOKIE["bfhash"];

            BruteForceProtection::InitializeUser($userID,$hash,$intIP);
        }
        public static function BlacklistCheck($accountID)
        {
            if(BruteForceProtection::$isBan == true)
            {
                if(BruteForceProtection::WhiteListCheck($accountID))
                {
                    return false;
                }
                return true;
            }
            return false;
        }
        public static function AddWrongTry()
        {
            if(BruteForceProtection::$whitelistdata != null)
            {
                BruteForceProtection::CurrentWhiteListRemoveTry();
                return;
            }
            BruteForceProtection::CurrentUserWrongTry();
            if(BruteForceProtection::$userdata['hash'])
            {
                setcookie("bfid", BruteForceProtection::$userdata['id'], time()+604800, "/", ".exemple.com", true);
                setcookie("bfhash", BruteForceProtection::$userdata['hash'], time()+604800, "/", ".exemple.com", true);
            }
            return;
        }
    }
?>