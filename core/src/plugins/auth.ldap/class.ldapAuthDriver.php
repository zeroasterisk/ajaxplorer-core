<?php
/*
 * Copyright 2007-2011 Pierre Wirtz
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * Authenticate users against an LDAP server
 */
class ldapAuthDriver extends AbstractAuthDriver {

    var $ldapUrl;
    var $ldapPort = 389;
    var $ldapAdminUsername;
    var $ldapAdminPassword;
    var $ldapDN;
    var $ldapFilter;
    var $ldapUserAttr;

    var $ldapconn = null;
    var $separateGroup = "";

    var $customParamsMapping = array();

    function init($options){
        parent::init($options);
        $options = $this->options;
        $this->ldapUrl = $options["LDAP_URL"];
        if ($options["LDAP_PORT"]) $this->ldapPort = $options["LDAP_PORT"];
        if ($options["LDAP_USER"]) $this->ldapAdminUsername = $options["LDAP_USER"];
        if ($options["LDAP_PASSWORD"]) $this->ldapAdminPassword = $options["LDAP_PASSWORD"];
        if ($options["LDAP_DN"]) $this->ldapDN = $options["LDAP_DN"];
        if (is_array($options["CUSTOM_DATA_MAPPING"])) $this->customParamsMapping = $options["CUSTOM_DATA_MAPPING"];
        if (isSet($options["LDAP_FILTER"])){
            $this->ldapFilter = $options["LDAP_FILTER"];
            if ($this->ldapFilter != "" &&  !preg_match("/^\(.*\)$/", $this->ldapFilter)) {
                $this->ldapFilter = "(" . $this->ldapFilter . ")";
            }
        } else {
            $this->ldapFilter = "(objectClass=person)";
        }
        if ($options["LDAP_USERATTR"]){
			$this->ldapUserAttr = strtolower($options["LDAP_USERATTR"]);
		}else{ 
			$this->ldapUserAttr = 'uid' ; 
		}
        /*
        $this->ldapconn = $this->LDAP_Connect();
        if ($this->ldapconn == null) AJXP_Logger::logAction('LDAP Server connexion could NOT be established');
        */
    }

    function startConnexion(){
        AJXP_Logger::logAction('Auth.ldap :: init');
        if($this->ldapconn == null){
            $this->ldapconn = $this->LDAP_Connect();
            if($this->ldapconn == null) {
                AJXP_Logger::logAction('LDAP Server connexion could NOT be established');
            }
        }
        //return $this->ldapconn;
    }

    function __deconstruct(){
        //@todo : if PHP server < 5, this method will never be closed. Maybe use a close() method ?
        if($this->ldapconn != null){
            ldap_close($this->ldapconn);
        }
    }

    function LDAP_Connect(){
        $ldapconn = ldap_connect($this->ldapUrl, $this->ldapPort)
        or die("Cannot connect to LDAP server");
        //@todo : return error_code

        if ($ldapconn) {
            //AJXP_Logger::logAction("auth.ldap:We are connected");
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);

            if ($this->ldapAdminUsername === null){
                //connecting anonymously
                AJXP_Logger::logAction('Anonymous LDAP connexion');
                $ldapbind = @ldap_bind($ldapconn);
            } else {
                AJXP_Logger::logAction('Standard LDAP connexion');
                $ldapbind = @ldap_bind($ldapconn, $this->ldapAdminUsername, $this->ldapAdminPassword);
            }

            if ($ldapbind){
                return $ldapconn;
            } else {
                return null;
            }
            
        } else {
            AJXP_Logger::logAction("Error while connection to LDAP server");
        }

    }


    function getUserEntries($login = null, $countOnly = false, $offset = -1, $limit = -1){
        if ($login == null){
            $filter = $this->ldapFilter;
        } else {
            if($this->ldapFilter == "") $filter = "(" . $this->ldapUserAttr . "=" . $login . ")";
            else  $filter = "(&" . $this->ldapFilter . "(" . $this->ldapUserAttr . "=" . $login . "))";
        }
        $this->startConnexion();
        $conn = array();
        if(is_array($this->ldapDN)){
            foreach($this->ldapDN as $dn){
                $conn[] = $this->ldapconn;
            }
        }else{
            $conn = array($this->ldapconn);
        }
        $expected = array($this->ldapUserAttr);
        if($login != null && !empty($this->customParamsMapping)){
            $expected = array_merge($expected, array_keys($this->customParamsMapping));
        }
        $ret = ldap_search($conn,$this->ldapDN,$filter, $expected);
        $allEntries = array("count" => 0);
        foreach($ret as $resourceResult){
            if($countOnly){
                $allEntries["count"] += ldap_count_entries($this->ldapconn, $resourceResult);
                continue;
            }
            $entries = ldap_get_entries($this->ldapconn, $resourceResult);
            $index = 0;
            if(!empty($entries["count"])){
                $allEntries["count"] += $entries["count"];
                unset($entries["count"]);
                foreach($entries as $entry){
                    if($offset != -1 && $index < $offset){
                        $index ++; continue;
                    }
                    $allEntries[] = $entry;
                    $index ++;
                    if($limit!= -1 && $index >= $offset + $limit) break;
                }
            }
        }
        return $allEntries;
    }

    function supportsUsersPagination(){
        return true;
    }
    function listUsersPaginated($baseGroup="/", $regexp, $offset, $limit){

        if($baseGroup != "/".$this->separateGroup) return array();

        if($regexp[0]=="^") $regexp = ltrim($regexp, "^")."*";
        else if($regexp[strlen($regexp)-1] == "$") $regexp = "*".rtrim($regexp, "$");

        $entries = $this->getUserEntries($regexp, false, $offset, $limit);
        $persons = array();
        unset($entries['count']); // remove 'count' entry
        foreach($entries as $id => $person){
            $login = $person[$this->ldapUserAttr][0];
            if(AuthService::ignoreUserCase()) $login = strtolower($login);
            $persons[$login] = "XXX";
        }
        return $persons;
    }
    function getUsersCount(){
        $res = $this->getUserEntries(null, true, null);
        return $res["count"];
    }


    /**
     * List children groups of a given group. By default will report this on the CONF driver,
     * but can be overriden to grab info directly from auth driver (ldap, etc).
     * @param string $baseGroup
     * @return string[]
     */
    function listChildrenGroups($baseGroup = "/"){
        $arr = array();
        if($baseGroup == "/" && !empty($this->separateGroup)) $arr[$this->separateGroup] = "LDAP Annuary";
        return $arr;
    }


    function listUsers($baseGroup = "/"){
        if($baseGroup != "/".$this->separateGroup) return array();
		$entries = $this->getUserEntries();
        $persons = array();
        unset($entries['count']); // remove 'count' entry
        foreach($entries as $id => $person){
            $login = $person[$this->ldapUserAttr][0];
            if(AuthService::ignoreUserCase()) $login = strtolower($login);
            $persons[$login] = "XXX";
        }
        return $persons;
    }

	function userExists($login){
        $entries = $this->getUserEntries($login);
        if(!is_array($entries)) return false;
        if(AuthService::ignoreUserCase()) {
            return (strcasecmp($login, $entries[0][$this->ldapUserAttr][0]) == 0);
        }else {
            return (strcmp($login, $entries[0][$this->ldapUserAttr][0]) == 0 );
        }
    }

    function checkPassword($login, $pass, $seed){

        if(empty($pass)) return false;
        $entries = $this->getUserEntries($login);
        if ($entries['count']>0) {
            if (@ldap_bind($this->ldapconn,$entries[0]["dn"],$pass)) {
                AJXP_Logger::logAction('Ldap Password Check:Got user '.$entries[0]["cn"][0]);
                return true;
            }
            return false;
        } else {
            AJXP_Logger::logAction("Ldap Password Check:No user $login found");
            return false;
        }
    }

    function usersEditable(){
        return false;
    }
    function passwordsEditable(){
        return false;
    }

    function updateUserObject(&$userObject){
        if(!empty($this->separateGroup)) $userObject->setGroupPath("/".$this->separateGroup);
        if(!empty($this->customParamsMapping)){
            $checkValues =  array_values($this->customParamsMapping);
            $prefs = $userObject->getPref("CUSTOM_PARAMS");
            if(!is_array($prefs)) {
                $prefs = array();
            }
            // If one value exist, we consider the mapping has already been done.
            foreach($checkValues as $val){
                if(array_key_exists($val, $prefs)) return;
            }
            $changes = false;
            $entries = $this->getUserEntries($userObject->getId());
            if($entries["count"]){
                $entry = $entries[0];
                foreach($this->customParamsMapping as $key => $value){
                    if(isSet($entry[$key])){
                        $prefs[$value] = $entry[$key][0];
                        $changes = true;
                    }
                }
            }
            if($changes){
                $userObject->setPref("CUSTOM_PARAMS", $prefs);
                $userObject->save();
            }
        }
    }

}