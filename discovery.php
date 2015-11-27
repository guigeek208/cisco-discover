<?php

	$listNasTodo = array();
	$listNasDone = array();
	$results = array();
    /* Parameter to enter */
    $nasname = "";
	$login = "";
	$password = "";
	$enablepassword = "";
    /* */
    $cmd="./scripts/command.sh $nasname hostname $login $password $enablepassword";
    $return = shell_exec($cmd);
    $hostname = trim($return);
    $listNasTodo[$hostname] = $nasname;
	
	while(1==1) {
		if (count($listNasTodo) > 0) {
			foreach ($listNasTodo as $hostname=>$nas) {
				$results[$hostname] = getInfosNasCDP($nas, $login, $password, $enablepassword);
				unset($listNasTodo[$hostname]);
				foreach ($results[$hostname] as $neigh) {
					if (!array_key_exists($neigh['hostname'], $results) && !array_key_exists($neigh['hostname'], $listNasTodo)) {
						if (preg_match('/Switch/', $neigh['capabilities'], $matches)) {
							if (isset($neigh['ipaddress'])) {
								$listNasTodo[$neigh['hostname']] = $neigh['ipaddress'];
							}
						}
					}
					if (!array_key_exists($neigh['hostname'], $listNasDone)) {
						if (isset($neigh['ipaddress'])) {
							$listNasDone[$neigh['hostname']]['ipaddress'] = $neigh['ipaddress'];
						}
						if (isset($neigh['platform'])) {
							$listNasDone[$neigh['hostname']]['platform'] = $neigh['platform'];
						}
						if (isset($neigh['version'])) {
							$listNasDone[$neigh['hostname']]['version'] = $neigh['version'];
						}
					}
				}
			}
		} else {
			break;
		}
	}
	
	//var_dump($listNasDone);
	$string = "";
	foreach($listNasDone as $hostname=>$nas) {
		$string .= $hostname.",";
		$string .= isset($nas['ipaddress']) ? $nas['ipaddress'] : "";
		$string .= ",";
		$string .= isset($nas['platform']) ? $nas['platform'] : "";
		$string .= ",";
		$string .= isset($nas['version']) ? $nas['version'] : "";
		$string .= "\n";
	}
	file_put_contents("inventaire.csv", $string);
	createGraph($results);

    function getInfosNasCDP($nasname, $login, $password, $enablepassword) {
        $results = array();
        $cmd="./scripts/command.sh $nasname cdp $login $password $enablepassword";
        $return = shell_exec($cmd);
        $infos = explode("\n", $return);
        //var_dump($infos);
        //$BEGINS = false;
        $results = array();
        $res = array();
        foreach($infos as $line) {
            $info = trim($line);
            if (preg_match('/-------------------------/', $info, $matches)) {
                if (count($res) > 0) {
                    $results[] = $res;
                    $res = array();
                }
            } else {
                $res[] = $info;
            }
        }
        if (count($res) > 0) {
            $results[] = $res;
        }
        //var_dump($results);
        $listNAS = array();
        $id = 0;
        foreach($results as $result) {
            foreach ($result as $info) {
                if (preg_match('/Device\s+ID\s*:\s*(.*)/', $info, $matches)) {
                    $infos = explode(".", $matches[1]);
                    $listNAS[$id]['hostname'] = $infos[0];
                }
                if (preg_match('/IP\s+address\s*:\s+(.*)/',$info, $matches)) {
                    if ($listNAS[$id]['hostname'] != "") {
                        $listNAS[$id]['ipaddress'] = $matches[1];
                    }
                }
                if (preg_match('/Platform\s*:\s+(.*),\s*Capabilities:\s*(.*)/',$info, $matches)) {
                    if ($listNAS[$id]['hostname'] != "") {
                        $listNAS[$id]['platform'] = $matches[1];
                        $listNAS[$id]['capabilities'] = $matches[2];
                    }
                }
                if (preg_match('/Version\s*(.*),/',$info, $matches)) {
                    if ($listNAS[$id]['hostname'] != "") {
                        $listNAS[$id]['version'] = $matches[1];
                    }
                }
                if (preg_match('/CCM:(.*)/',$info, $matches)) {
                    if ($listNAS[$id]['hostname'] != "") {
                        $listNAS[$id]['version'] = $matches[1];
                    }
                }
                if (preg_match('/Product Version:\s*(.*)\s+/',$info, $matches)) {
                    if ($listNAS[$id]['hostname'] != "") {
                        $listNAS[$id]['version'] = $matches[1];
                    }
                }
                if (preg_match('/(SCCP.*)/',$info, $matches)) {
                    if ($listNAS[$id]['hostname'] != "") {
                        $listNAS[$id]['version'] = $matches[1];
                    }
                }
                if (preg_match('/(SIP.*)/',$info, $matches)) {
                    if ($listNAS[$id]['hostname'] != "") {
                        $listNAS[$id]['version'] = $matches[1];
                    }
                }
                if (preg_match('/Product Version:\s*(.*)\s+/',$info, $matches)) {
                    if ($listNAS[$id]['hostname'] != "") {
                        $listNAS[$id]['version'] = $matches[1];
                    }
                }
                if (preg_match('/Interface\s*:\s*(.*),\s+Port\s+ID\s*\(outgoing port\):\s+(.*)/',$info, $matches)) {
                    if ($listNAS[$id]['hostname'] != "") {
                        $tmp = str_replace("GigabitEthernet", "Gi", $matches[1]);
                        $tmp2 = str_replace("FastEthernet", "Fa", $tmp);
                        $localinterface = str_replace("Ethernet", "Eth", $tmp2);
                        $tmp = str_replace("GigabitEthernet", "Gi", $matches[2]);
                        $tmp2 = str_replace("FastEthernet", "Fa", $tmp);
                        $remoteinterface = str_replace("Ethernet", "Eth", $tmp2);
                        $listNAS[$id]['localinterface'] = $localinterface;
                        $listNAS[$id]['remoteinterface'] = $remoteinterface;
                    }
                }
            }
            $id++;
        }
        return $listNAS;
    }

    
    function createGraph($listNAS) {
        $string="graph network {\noverlap = false;\n";
        $listshapes = array();
        foreach ($listNAS as $hostname=>$listneigh) {
            foreach($listneigh as $neigh) {
                if (!preg_match('/"'.$neigh['hostname'].'"'.'--'.'"'.$hostname.'"/', $string, $matches)) {
                    $string .= '"'.$hostname.'"'.'--'.'"'.$neigh['hostname'].'"'."\n";
                    $string .= 'edge [fontsize=9 headlabel = "        '.$neigh['remoteinterface'].'", taillabel = "'.$neigh['localinterface'].'        "]'."\n";
                }
                if (!array_key_exists($neigh['hostname'], $listshapes)) {
                    if (preg_match('/Host/', $neigh['capabilities'], $matches)) { 
                        $listshapes[$neigh['hostname']] = "Host";
                    }
                    if (preg_match('/Phone/', $neigh['capabilities'], $matches)) {
                        $listshapes[$neigh['hostname']] = "Phone";
                    }
                    if (preg_match('/Switch/', $neigh['capabilities'], $matches)) { 
			            if (preg_match('/VMware ESX/', $neigh['platform'], $matches)) {
                            $listshapes[$neigh['hostname']] = "Host";
                        } else {
                            $listshapes[$neigh['hostname']] = "Switch";
                        }
                    }
                    if (preg_match('/Trans-Bridge/', $neigh['capabilities'], $matches)) { 
                        $listshapes[$neigh['hostname']] = "Wifi";
                    }
                }
            }
        }
	   $path = "img/";
        foreach ($listshapes as $hostname=>$type) {
            if ($type == "Phone") {
                $string .= '"'.$hostname.'" [shape=none, image="'.$path.'ipphone.png", label="'.$hostname.'"]'."\n";
            }
            if ($type == "Switch") {
                $string .= '"'.$hostname.'" [shape=none, image="'.$path.'switch.png", label="'.$hostname.'"]'."\n";
            }
            if ($type == "Wifi") {
                $string .= '"'.$hostname.'" [shape=none, image="'.$path.'wifi.png", label="'.$hostname.'"]'."\n";
            }
            if ($type == "Host") {
                $string .= '"'.$hostname.'" [shape=none, image="'.$path.'server.png", label="'.$hostname.'"]'."\n";
            }
        }
        $string .= "}";
        file_put_contents("network.dot", $string);
        $cmd = "neato -Tpng -O network.dot -o network";
        shell_exec($cmd);
    }

    function getInfosAllNasCDP() {
        $key = Configure::read('Security.snackkey');
        $nas = $this->find('all');
        $results = array();
        foreach ($nas as $n) {
            if ($n['Nas']['nasname'] != "127.0.0.1") {
                if (($n['Nas']['login'] != "") && ($n['Nas']['password'] != "")) {
                    $password = $this->getPassword($n['Nas']['password']);
                    if ($n['Nas']['enablepassword'] != "") {
                        $enablepassword = $this->getPassword($n['Nas']['enablepassword']);
                    }
                    if (($n['Nas']['enablepassword'] == "") || ($enablepassword == "")) {
                        $enablepassword = $password;
                    }
                    if ($password != "") {
                        $results[$n['Nas']['shortname']] = $this->getInfosNasCDP($n['Nas']['nasname'], $n['Nas']['login'], $password, $enablepassword);
                    }
                }
            }
        }
        $this->createGraph($results);
        return $results;
    }




?>
