<?php
/*
    PufferPanel - A Minecraft Server Management Panel
    Copyright (c) 2013 Dane Everitt
 
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program.  If not, see http://www.gnu.org/licenses/.
 */
session_start();
require_once('../../../../src/framework/framework.core.php');

if($core->auth->isLoggedIn($_SERVER['REMOTE_ADDR'], $core->auth->getCookie('pp_auth_token'), $core->auth->getCookie('pp_server_hash')) === true){
	
	/*
	 * Open Stream for Reading/Writing
	 */	
	$rewrite = false;						
	
	$url = "http://".$core->server->nodeData('sftp_ip').":8003/gameservers/".$core->server->getData('gsd_id')."/file/server.properties";
	
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($curl, CURLOPT_HTTPHEADER, array(                                                                          
	    'X-Access-Token: '.$core->server->nodeData('gsd_secret')
	));
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($curl);

	if(!$response)
		exit("An error was encountered with this AJAX request. (No Response)");
	
	$json = json_decode($response, true);
	
		if(!array_key_exists('contents', $json)) {
		
			/*
			 * Create server.properties
			 */
			$generateProperties = '#Minecraft Server Properties
#Generated by PufferPanel
generator-settings=
op-permission-level=4
allow-nether=true
level-name=world
enable-query=true
allow-flight=false
announce-player-achievements=true
server-port='.$core->server->getData('server_port').'
query.port='.$core->server->getData('server_port').'
level-type=DEFAULT
enable-rcon=false
force-gamemode=false
level-seed=
server-ip='.$core->server->getData('server_ip').'
max-build-height=256
spawn-npcs=true
white-list=false
debug=false
spawn-animals=true
texture-pack=
snooper-enabled=true
hardcore=false
online-mode=true
resource-pack=
pvp=true
difficulty=1
enable-command-block=false
gamemode=1
player-idle-timeout=0
max-players=20
spawn-monsters=true
generate-structures=true
view-distance=10
spawn-protection=16
motd=A Minecraft Server';

			$data = array("contents" => $generateProperties);
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(                                                                          
			    'X-Access-Token: '.$core->server->nodeData('gsd_secret')
			));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
			$response = curl_exec($curl);
			
		        if(!empty($response))
		        	exit("An error was encountered with this AJAX request. Unable to make server.properties.");
					
			$core->log->getUrl()->addLog(0, 1, array('system.create_serverprops', 'A new server.properties file was created for your server.'));
			 
		}
		
		$lines = explode("\n", $json['contents']);
		$newContents = $json['contents'];
		foreach($lines as $line){
		
			$var = explode('=', $line);
			
				if($var[0] == 'server-port' && $var[1] != $core->server->getData('server_port')){
					//Reset Port
					$newContents = str_replace('server-port='.$var[1], "server-port=".$core->server->getData('server_port')."\n", $newContents);
					$rewrite = true;
				}else if($var[0] == 'online-mode' && $var[1] == 'false'){
					if($core->settings->get('force_online') == 1){
						//Force Online Mode
						$newContents = str_replace('online-mode='.$var[1], "online-mode=true\n", $newContents);
						$rewrite = true;
					}
				}else if($var[0] == 'enable-query' && $var[1] != 'true'){
					//Reset Query Port
					$newContents = str_replace('enable-query='.$var[1], "enable-query=true\n", $newContents);
					$rewrite = true;
				}else if($var[0] == 'query.port' && $var[1] != $core->server->getData('server_port')){
					//Reset Query Port
					$newContents = str_replace('query.port='.$var[1], "query.port=".$core->server->getData('server_port')."\n", $newContents);
					$rewrite = true;
				}else if($var[0] == 'server-ip' && $var[1] != $core->server->getData('server_ip')){
					//Reset Query Port
					$newContents = str_replace('server-ip='.$var[1], "server-ip=".$core->server->getData('server_ip')."\n", $newContents);
					$rewrite = true;
				}
		
		}
			
				/*
				 * Write New Data
				 */
				if($rewrite === true){
							
					$data = array("contents" => $newContents);
					$curl = curl_init($url);
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
					curl_setopt($curl, CURLOPT_HTTPHEADER, array(                                                                          
					    'X-Access-Token: '.$core->server->nodeData('gsd_secret')
					));
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
					$response = curl_exec($curl);
					
					    if(!empty($response))
					    	exit("An error was encountered with this AJAX request. Unable to update server.properties.");
					    		
                    $core->log->getUrl()->addLog(0, 0, array('system.serverprops_updated', 'The server properties file was updated to match the assigned information.'));
					
				}

    /*
	 * Connect and Run Function
	 */
	$context = stream_context_create(array(
		"http" => array(
			"method" => "GET",
			"header" => 'X-Access-Token: '.$core->server->getData('gsd_secret'),
			"timeout" => 3
		)
	));
	$gatherData = @file_get_contents("http://".$core->server->nodeData('sftp_ip').":8003/gameservers/".$core->server->getData('gsd_id')."/on", 0, $context);

	if($gatherData != "\"ok\"")
		exit("An error was encountered with this AJAX request. ($gatherData)");
			
	/*
	 * Run CPU Limit
	 * cpulimit -p #### -l #### -d
	 *
	 * This is super buggy.
	 */
//	if($core->server->getData('cpu_limit') > 0){
//	
//		$gatherData = @file_get_contents("http://".$core->server->nodeData('sftp_ip').":8003/gameservers/".$core->server->getData('gsd_id'), 0, $context);
//		
//		$data = json_decode($gatherData, true);
//		
//			if(!array_key_exists('pid', $data))
//				exit("Unable to get PID. Server has been started.");
//		
//			$core->ssh->generateSSH2Connection($core->server->nodeData('id'), true)->executeSSH2Command('sudo cpulimit -p '.$data['pid'].' -l '.$core->server->getData('cpu_limit').' -d');
//					
//	}
	
	echo 'ok';
		
}else{

	die('Invalid Authentication.');

}
?>