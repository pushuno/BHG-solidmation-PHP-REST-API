/*MIT License

Copyright (c) 2017 Joan Manuel Hidalgo (joanmanuelhpages@gmail.com)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

class BGH {
	public function returnToken() {
		if($_COOKIE['bgh_token']) {
			$ret = $_COOKIE['bgh_token'];
		} else {
			$response = get("https://bgh-services.solidmation.com/control/LoginPage.aspx/DoStandardLogin", array("user" => BGH_USER, "password" => BGH_PASS), "POST", "json", true);
			setcookie ("bgh_token", $response[0]["d"], time() + (3600*24*10));
			$ret = $response[0]["d"];
		}
		$this->bgh_token = $ret; 
		return $ret;
	}

	public function getHomeId() {
		if($_COOKIE['bgh_home_id']) {
			$ret = $_COOKIE['bgh_home_id'];
		} else {
			$response = get("https://bgh-services.solidmation.com/1.0/HomeCloudService.svc/EnumHomes", array("token" => array("Token" => $this->returnToken())), "POST", "json", true);
			setcookie ("bgh_home_id", $response["EnumHomesResult"]["Homes"][0]["HomeID"], time() + (3600*24*10));
			$ret = $response["EnumHomesResult"]["Homes"][0]["HomeID"];
		}
		$this->bgh_home_id = $ret; 
		return $ret;
	}	
	
	public function getDevices() {
		set_time_limit(0);
		$turnData = '{"token":{"Token":"'.$this->returnToken().'"},"homeID":'.$this->getHomeId().',"timeOut":10000,"serials":{"Home":0,"Groups":0,"Devices":0,"Endpoints":0,"EndpointValues":0,"Scenes":0,"Macros":0,"Alarms":0}}';
		$response = get("https://bgh-services.solidmation.com/1.0/HomeCloudService.svc/GetDataPacket", json_decode($turnData), "POST", "json");
		
		$endpoints = array();
		for ($i = 0; $i < count($response['GetDataPacketResult']['EndpointValues']); $i++) {
			$endpoints[$i] = array("endpointID" => $response['GetDataPacketResult']['EndpointValues'][$i]["EndpointID"],
								 "turned" => $response['GetDataPacketResult']['EndpointValues'][$i]["Values"][1]["Value"],
								 "room" => $response['GetDataPacketResult']['EndpointValues'][$i]["Values"][0]["Value"], 
								 "air" => $response['GetDataPacketResult']['EndpointValues'][$i]["Values"][6]["Value"],
								 "name" => $response['GetDataPacketResult']['Endpoints'][$i]['Groups'][0]['Description'],
								 "device" => $response['GetDataPacketResult']['Endpoints'][$i]['Description'],
								 "homeID" => $this->getHomeId());
		}
		if(count($endpoints) == 0) throw new Exception('No devices found.');
		
		return $endpoints;
	}
	
	public function sendCommand($ops) {
		$temperature = $ops["temperature"] ? $ops["temperature"] : 24;
		$fan = $ops["fan"] ? $ops["fan"] : 254;
		$mode = $ops["mode"] ? $ops["mode"] : "on";
		$endpoint = $ops['endpoint'] ? $ops['endpoint'] : $this->getDevices()[0]['endpointID'];
		$turnData = '{"token":{"Token":"'.$this->returnToken().'"},"endpointID":'.$endpoint.',"desiredTempC":"'.$temperature.'","mode":"'.(($mode == "on")?1:0).'","fanMode":"'.$fan.'","flags":255}';
		$response = get("https://bgh-services.solidmation.com/1.0/HomeCloudCommandService.svc/HVACSetModes", json_decode($turnData), "POST", "json");
		
		return $response['HVACSetModesResult']['Result'];
	}
}
