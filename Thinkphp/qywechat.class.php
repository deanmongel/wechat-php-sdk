<?php
/**
 *	微信公众平台企业号PHP-SDK, 官方API类库
 *  @author  binsee <binsee@163.com>
 *  @link https://github.com/binsee/wechat-php-sdk
 *  @version 1.0
 *  usage:
 *   $options = array(
 *			'token'=>'tokenaccesskey', //填写应用接口的Token
 *			'encodingaeskey'=>'encodingaeskey', //填写加密用的EncodingAESKey
 *			'appid'=>'wxdk1234567890', //填写高级调用功能的app id
 *			'appsecret'=>'xxxxxxxxxxxxxxxxxxx', //填写高级调用功能的密钥
 *			'agentid'=>'1', //应用的id
 *			'debug'=>false, //调试开关
 *			'_logcallback'=>'logg', //调试输出方法，需要有一个string类型的参数
 *		);
 *
 */
class Wechat
{
    const MSGTYPE_TEXT = 'text';
    const MSGTYPE_IMAGE = 'image';
    const MSGTYPE_LOCATION = 'location';
    const MSGTYPE_LINK = 'link';    //暂不支持
    const MSGTYPE_EVENT = 'event';
    const MSGTYPE_MUSIC = 'music';    //暂不支持
    const MSGTYPE_NEWS = 'news';
    const MSGTYPE_VOICE = 'voice';
    const MSGTYPE_VIDEO = 'video';
    const API_URL_PREFIX = 'https://qyapi.weixin.qq.com/cgi-bin';
    const USER_CREATE_URL = '/user/create?';
    const USER_UPDATE_URL = '/user/update?';
    const USER_DELETE_URL = '/user/delete?';
    const USER_GET_URL = '/user/get?';
    const USER_LIST_URL = '/user/simplelist?';
    const USER_GETINFO_URL = '/user/getuserinfo?';
    const DEPARTMENT_CREATE_URL = '/department/create?';
    const DEPARTMENT_UPDATE_URL = '/department/update?';
    const DEPARTMENT_DELETE_URL = '/department/delete?';
    const DEPARTMENT_LIST_URL = '/department/LIST?';
    const TAG_CREATE_URL = '/tag/create?';
    const TAG_UPDATE_URL = '/tag/update?';
    const TAG_DELETE_URL = '/tag/delete?';
    const TAG_GET_URL = '/tag/get?';
    const TAG_ADDUSER_URL = '/tag/addtagusers?';
    const TAG_DELUSER_URL = '/tag/deltagusers?';
    const MEDIA_UPLOAD_URL = '/media/upload?';
    const MEDIA_GET_URL = '/media/get?';
    const AUTHSUCC_URL = '/user/authsucc?';
    const MASS_SEND_URL = '/message/send?';
    const MENU_CREATE_URL = '/menu/create?';
    const MENU_GET_URL = '/menu/get?';
    const MENU_DELETE_URL = '/menu/delete?';
    const TOKEN_GET_URL = '/gettoken?';
	const OAUTH_PREFIX = 'https://open.weixin.qq.com/connect/oauth2';
	const OAUTH_AUTHORIZE_URL = '/authorize?';
	
	private $token;
	private $encodingAesKey;
	private $appid;         //也就是企业号的CorpID
	private $appsecret;
	private $access_token;
    private $agentid;       //应用id   AgentID
	private $postxml;
    private $agentidxml;    //接收的应用id   AgentID	
	private $_msg;
	private $_receive;
	private $_sendmsg;      //主动发送消息的内容
	private $_text_filter = true;
	public $debug =  false;
	public $errCode = 40001;
	public $errMsg = "no access";
	private $_logcallback;
	
	public function __construct($options)
	{
		$this->token = isset($options['token'])?$options['token']:'';
		$this->encodingAesKey = isset($options['encodingaeskey'])?$options['encodingaeskey']:'';
		$this->appid = isset($options['appid'])?$options['appid']:'';
		$this->appsecret = isset($options['appsecret'])?$options['appsecret']:'';
		$this->agentid = isset($options['agentid'])?$options['agentid']:'';
		$this->debug = isset($options['debug'])?$options['debug']:false;
		$this->_logcallback = isset($options['logcallback'])?$options['logcallback']:false;
	}
	
	private function log($log){
	    if ($this->debug ) {
	        if (function_exists($this->_logcallback)) {
	            if (is_array($log)) $log = print_r($log,true);
	            return call_user_func($this->_logcallback,$log);
	        }elseif (class_exists('Log')) {
	            Log::write('qywechat：'.$log, Log::DEBUG);
	        }
	    }
	    return false;
	}

	/**
	 * 数据XML编码
	 * @param mixed $data 数据
	 * @return string
	 */
	public static function data_to_xml($data) {
	    $xml = '';
	    foreach ($data as $key => $val) {
	        is_numeric($key) && $key = "item id=\"$key\"";
	        $xml    .=  "<$key>";
	        $xml    .=  ( is_array($val) || is_object($val)) ? self::data_to_xml($val)  : self::xmlSafeStr($val);
	        list($key, ) = explode(' ', $key);
	        $xml    .=  "</$key>";
	    }
	    return $xml;
	}
	
	public static function xmlSafeStr($str)
	{
	    return '<![CDATA['.preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/",'',$str).']]>';
	}
	
	/**
	 * XML编码
	 * @param mixed $data 数据
	 * @param string $root 根节点名
	 * @param string $item 数字索引的子节点名
	 * @param string $attr 根节点属性
	 * @param string $id   数字索引子节点key转换的属性名
	 * @param string $encoding 数据编码
	 * @return string
	 */
	public function xml_encode($data, $root='xml', $item='item', $attr='', $id='id', $encoding='utf-8') {
	    if(is_array($attr)){
	        $_attr = array();
	        foreach ($attr as $key => $value) {
	            $_attr[] = "{$key}=\"{$value}\"";
	        }
	        $attr = implode(' ', $_attr);
	    }
	    $attr   = trim($attr);
	    $attr   = empty($attr) ? '' : " {$attr}";
	    $xml   = "<{$root}{$attr}>";
	    $xml   .= self::data_to_xml($data, $item, $id);
	    $xml   .= "</{$root}>";
	    return $xml;
	}
	

	/**
	 * 微信api不支持中文转义的json结构
	 * @param array $arr
	 */
	static function json_encode($arr) {
	    $parts = array ();
	    $is_list = false;
	    //Find out if the given array is a numerical array
	    $keys = array_keys ( $arr );
	    $max_length = count ( $arr ) - 1;
	    if (($keys [0] === 0) && ($keys [$max_length] === $max_length )) { //See if the first key is 0 and last key is length - 1
	        $is_list = true;
	        for($i = 0; $i < count ( $keys ); $i ++) { //See if each key correspondes to its position
	            if ($i != $keys [$i]) { //A key fails at position check.
	                $is_list = false; //It is an associative array.
	                break;
	            }
	        }
	    }
	    foreach ( $arr as $key => $value ) {
	        if (is_array ( $value )) { //Custom handling for arrays
	            if ($is_list)
	                $parts [] = self::json_encode ( $value ); /* :RECURSION: */
	            else
	                $parts [] = '"' . $key . '":' . self::json_encode ( $value ); /* :RECURSION: */
	        } else {
	            $str = '';
	            if (! $is_list)
	                $str = '"' . $key . '":';
	            //Custom handling for multiple data types
	            if (is_numeric ( $value ) && $value<2000000000)
	                $str .= $value; //Numbers
	            elseif ($value === false)
	            $str .= 'false'; //The booleans
	            elseif ($value === true)
	            $str .= 'true';
	            else
	                $str .= '"' . addslashes ( $value ) . '"'; //All other things
	            // :TODO: Is there any more datatype we should be in the lookout for? (Object?)
	            $parts [] = $str;
	        }
	    }
	    $json = implode ( ',', $parts );
	    if ($is_list)
	        return '[' . $json . ']'; //Return numerical JSON
	    return '{' . $json . '}'; //Return associative JSON
	}
	
	/**
	 * 过滤文字回复\r\n换行符
	 * @param string $text
	 * @return string|mixed
	 */
	private function _auto_text_filter($text) {
	    if (!$this->_text_filter) return $text;
	    return str_replace("\r\n", "\n", $text);
	}
	
	/**
	 * GET 请求
	 * @param string $url
	 */
	private function http_get($url){
	    $oCurl = curl_init();
	    if(stripos($url,"https://")!==FALSE){
	        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
	        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
	    }
	    curl_setopt($oCurl, CURLOPT_URL, $url);
	    curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
	    $sContent = curl_exec($oCurl);
	    $aStatus = curl_getinfo($oCurl);
	    curl_close($oCurl);
	    if(intval($aStatus["http_code"])==200){
	        return $sContent;
	    }else{
	        return false;
	    }
	}
	
	/**
	 * POST 请求
	 * @param string $url
	 * @param array $param
	 * @return string content
	 */
	private function http_post($url,$param){
	    $oCurl = curl_init();
	    if(stripos($url,"https://")!==FALSE){
	        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
	        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
	    }
	    if (is_string($param)) {
	        $strPOST = $param;
	    } else {
	        $aPOST = array();
	        foreach($param as $key=>$val){
	            $aPOST[] = $key."=".urlencode($val);
	        }
	        $strPOST =  join("&", $aPOST);
	    }
	    curl_setopt($oCurl, CURLOPT_URL, $url);
	    curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
	    curl_setopt($oCurl, CURLOPT_POST,true);
	    curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
	    $sContent = curl_exec($oCurl);
	    $aStatus = curl_getinfo($oCurl);
	    curl_close($oCurl);
	    if(intval($aStatus["http_code"])==200){
	        return $sContent;
	    }else{
	        return false;
	    }
	}

	/**
	 * For weixin server validation
	 */
	private function checkSignature($str)
	{
	    $signature = isset($_GET["msg_signature"])?$_GET["msg_signature"]:'';
	    $timestamp = isset($_GET["timestamp"])?$_GET["timestamp"]:'';
	    $nonce = isset($_GET["nonce"])?$_GET["nonce"]:'';
	    $tmpArr = array($str,$this->token, $timestamp, $nonce);//比普通公众平台多了一个加密的密文
	    sort($tmpArr, SORT_STRING);
	    $tmpStr = implode($tmpArr);
	    $shaStr = sha1($tmpStr);
	    if( $shaStr == $signature ){
	        return true;
	    }else{
	        return false;
	    }
	}
	
	/**
	 * 微信验证，包括post来的xml解密
	 * @param bool $return 是否返回
	 */
	public function valid($return=false)
    {
        $encryptStr="";
        if ($_SERVER['REQUEST_METHOD'] == "POST") {
            $postStr = file_get_contents("php://input");
            $array = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $this->log($postStr);
            if (isset($array['Encrypt'])) {
                $encryptStr = $array['Encrypt'];
                $this->agentidxml = isset($array['AgentID']) ? $array['AgentID']: '';
            }
        } else {
            $encryptStr = isset($_GET["echostr"]) ? $_GET["echostr"]: '';

        }
        if ($encryptStr) {
            $ret=$this->checkSignature($encryptStr);
        }
        if (!isset($ret) || !$ret) {
        	if (!$return) {
        	    die('no access');
        	} else {
        	    return false;
        	}
        }
        $pc = new Prpcrypt($this->encodingAesKey);
        $array = $pc->decrypt($encryptStr,$this->appid);
        if (!isset($array[0]) || ($array[0] != 0)) {
            if (!$return) {
        	    die('解密失败！');
        	} else {
        	    return false;
        	}
        }
        if ($_SERVER['REQUEST_METHOD'] == "POST") {
            $this->postxml = $array[1];
            //$this->log($array[1]);
            return ($this->postxml!="");
        } else {
            $echoStr = $array[1];
            if ($return) {
            	return $echoStr;
            } else {
                die($echoStr);
            }
        }
        return false;
    }
    
    /**
     * 获取微信服务器发来的信息
     */
	public function getRev()
	{
		if ($this->_receive) return $this;
		$postStr = $this->postxml;
		$this->log($postStr);
		if (!empty($postStr)) {
			$this->_receive = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
			if (!isset($this->_receive['AgentID'])) {
			     $this->_receive['AgentID']=$this->agentidxml; //当前接收消息的应用id
			}
		}
		return $this;
	}
	
	/**
	 * 获取微信服务器发来的信息
	 */
	public function getRevData()
	{
		return $this->_receive;
	}
	
	/**
	 * 获取微信服务器发来的原始加密信息
	 */
	public function getRevPostXml()
	{
	    return $this->postxml;
	}
	
	/**
	 * 获取消息发送者
	 */
	public function getRevFrom() {
		if (isset($this->_receive['FromUserName']))
			return $this->_receive['FromUserName'];
		else 
			return false;
	}
	
	/**
	 * 获取消息接受者
	 */
	public function getRevTo() {
		if (isset($this->_receive['ToUserName']))
			return $this->_receive['ToUserName'];
		else 
			return false;
	}
	
	/**
	 * 获取接收消息的应用id
	 */
	public function getRevAgentID() {
		if (isset($this->_receive['AgentID']))
			return $this->_receive['AgentID'];
		else 
			return false;
	}

	/**
	 * 获取接收消息的类型
	 */
	public function getRevType() {
		if (isset($this->_receive['MsgType']))
			return $this->_receive['MsgType'];
		else 
			return false;
	}
	
	/**
	 * 获取消息ID
	 */
	public function getRevID() {
		if (isset($this->_receive['MsgId']))
			return $this->_receive['MsgId'];
		else 
			return false;
	}
	
	/**
	 * 获取消息发送时间
	 */
	public function getRevCtime() {
		if (isset($this->_receive['CreateTime']))
			return $this->_receive['CreateTime'];
		else 
			return false;
	}
	
	/**
	 * 获取接收消息内容正文
	 */
	public function getRevContent(){
		if (isset($this->_receive['Content']))
			return $this->_receive['Content'];
		else
			return false;
	}
	
	/**
	 * 获取接收消息图片
	 */
	public function getRevPic(){
		if (isset($this->_receive['PicUrl']))
			return array(
				'mediaid'=>$this->_receive['MediaId'],
				'picurl'=>(string)$this->_receive['PicUrl'],    //防止picurl为空导致解析出错
			);
		else 
			return false;
	}
	
	/**
	 * 获取接收地理位置
	 */
	public function getRevGeo(){
		if (isset($this->_receive['Location_X'])){
			return array(
				'x'=>$this->_receive['Location_X'],
				'y'=>$this->_receive['Location_Y'],
				'scale'=>(string)$this->_receive['Scale'],
				'label'=>(string)$this->_receive['Label']
			);
		} else 
			return false;
	}
	
	/**
	 * 获取上报地理位置事件
	 */
	public function getRevEventGeo(){
        	if (isset($this->_receive['Latitude'])){
        		 return array(
				'x'=>$this->_receive['Latitude'],
				'y'=>$this->_receive['Longitude'],
				'precision'=>$this->_receive['Precision'],
			);
		} else
			return false;
	}
	
	/**
	 * 获取接收事件推送
	 */
	public function getRevEvent(){
		if (isset($this->_receive['Event'])){
			$array['event'] = $this->_receive['Event'];
		}
		if (isset($this->_receive['EventKey'])){
			$array['key'] = $this->_receive['EventKey'];
		}
		if (isset($array) && count($array) > 0) {
			return $array;
		} else {
			return false;
		}
	}
	
	/**
	 * 获取接收语音推送
	 */
	public function getRevVoice(){
		if (isset($this->_receive['MediaId'])){
			return array(
				'mediaid'=>$this->_receive['MediaId'],
				'format'=>$this->_receive['Format'],
			);
		} else 
			return false;
	}
	
	/**
	 * 获取接收视频推送
	 */
	public function getRevVideo(){
		if (isset($this->_receive['MediaId'])){
			return array(
					'mediaid'=>$this->_receive['MediaId'],
					'thumbmediaid'=>$this->_receive['ThumbMediaId']
			);
		} else
			return false;
	}
	
	/**
	 * 设置回复消息
	 * Examle: $obj->text('hello')->reply();
	 * @param string $text
	 */
	public function text($text='')
	{
		$msg = array(
			'ToUserName' => $this->getRevFrom(),
			'FromUserName'=>$this->getRevTo(),
			'MsgType'=>self::MSGTYPE_TEXT,
			'Content'=>$this->_auto_text_filter($text),
			'CreateTime'=>time(),
		);
		$this->Message($msg);
		return $this;
	}
	
	/**
	 * 设置回复消息
	 * Examle: $obj->image('media_id')->reply();
	 * @param string $mediaid
	 */
	public function image($mediaid='')
	{
		$msg = array(
			'ToUserName' => $this->getRevFrom(),
			'FromUserName'=>$this->getRevTo(),
			'MsgType'=>self::MSGTYPE_IMAGE,
			'Image'=>array('MediaId'=>$mediaid),
			'CreateTime'=>time(),
		);
		$this->Message($msg);
		return $this;
	}
	
	/**
	 * 设置回复消息
	 * Examle: $obj->voice('media_id')->reply();
	 * @param string $mediaid
	 */
	public function voice($mediaid='')
	{
		$msg = array(
			'ToUserName' => $this->getRevFrom(),
			'FromUserName'=>$this->getRevTo(),
			'MsgType'=>self::MSGTYPE_IMAGE,
			'Voice'=>array('MediaId'=>$mediaid),
			'CreateTime'=>time(),
		);
		$this->Message($msg);
		return $this;
	}
	
	/**
	 * 设置回复消息
	 * Examle: $obj->video('media_id','title','description')->reply();
	 * @param string $mediaid
	 */
	public function video($mediaid='',$title,$description)
	{
		$msg = array(
			'ToUserName' => $this->getRevFrom(),
			'FromUserName'=>$this->getRevTo(),
			'MsgType'=>self::MSGTYPE_IMAGE,
			'Video'=>array(
			        'MediaId'=>$mediaid,
			        'Title'=>$mediaid,
			        'Description'=>$mediaid,
			),
			'CreateTime'=>time(),
		);
		$this->Message($msg);
		return $this;
	}
	
	/**
	 * 设置回复图文
	 * @param array $newsData 
	 * 数组结构:
	 *  array(
	 *  	"0"=>array(
	 *  		'Title'=>'msg title',
	 *  		'Description'=>'summary text',
	 *  		'PicUrl'=>'http://www.domain.com/1.jpg',
	 *  		'Url'=>'http://www.domain.com/1.html'
	 *  	),
	 *  	"1"=>....
	 *  )
	 */
	public function news($newsData=array())
	{

		$count = count($newsData);
		
		$msg = array(
			'ToUserName' => $this->getRevFrom(),
			'FromUserName'=>$this->getRevTo(),
			'MsgType'=>self::MSGTYPE_NEWS,
			'CreateTime'=>time(),
			'ArticleCount'=>$count,
			'Articles'=>$newsData,

		);
		$this->Message($msg);
		return $this;
	}
	
	/**
	 * 设置发送消息
	 * @param array $msg 消息数组
	 * @param bool $append 是否在原消息数组追加
	 */
	public function Message($msg = '',$append = false){
	    if (is_null($msg)) {
	        $this->_msg =array();
	    }elseif (is_array($msg)) {
	        if ($append)
	            $this->_msg = array_merge($this->_msg,$msg);
	        else
	            $this->_msg = $msg;
	        return $this->_msg;
	    } else {
	        return $this->_msg;
	    }
	}

	/**
	 * 
	 * 回复微信服务器, 此函数支持链式操作
	 * Example: $this->text('msg tips')->reply();
	 * @param string $msg 要发送的信息, 默认取$this->_msg
	 * @param bool $return 是否返回信息而不抛出到浏览器 默认:否
	 */
	public function reply($msg=array(),$return = false)
	{
		if (empty($msg)) 
			$msg = $this->_msg;
		$xmldata=  $this->xml_encode($msg);
		$this->log($xmldata);
		$pc = new Prpcrypt($this->encodingAesKey);
		$array = $pc->encrypt($xmldata, $this->appid);
		$ret = $array[0];
		if ($ret != 0) {
		    $this->log('encrypt err!');
		    return false;
		}
		$timestamp = time();
		$nonce = rand(77,999)*rand(605,888)*rand(11,99);
		$encrypt = $array[1];
		$tmpArr = array($this->token, $timestamp, $nonce,$encrypt);//比普通公众平台多了一个加密的密文
		sort($tmpArr, SORT_STRING);
		$signature = implode($tmpArr);
		$signature = sha1($signature);
		$smsg = $this->generate($encrypt, $signature, $timestamp, $nonce);
		$this->log($smsg);
		if ($return)
		    return $smsg;
		elseif ($smsg){
			echo $smsg;
		    return true;
		    
		}else 
		    return false;
	}

	private function generate($encrypt, $signature, $timestamp, $nonce)
	{
	    //格式化加密信息
	    $format = "<xml>
<Encrypt><![CDATA[%s]]></Encrypt>
<MsgSignature><![CDATA[%s]]></MsgSignature>
<TimeStamp>%s</TimeStamp>
<Nonce><![CDATA[%s]]></Nonce>
</xml>";
	    return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
	}
	

	/**
	 * 通用auth验证方法
	 * @param string $appid
	 * @param string $appsecret
	 * @param string $token 手动指定access_token，非必要情况不建议用
	 */
	public function checkAuth($appid='',$appsecret='',$token=''){
		if (!$appid || !$appsecret) {
			$appid = $this->appid;
			$appsecret = $this->appsecret;
		}
		$authname = 'qywechat_access_token'.$appid;
		if ($token) { //手动指定token，优先使用
		    $this->access_token=$token;
		    return $this->access_token;
		}
		if ($rs = S($authname))  {
			$this->access_token = $rs;
			return $rs;
		}
		$result = $this->http_get(self::API_URL_PREFIX.self::TOKEN_GET_URL.'corpid='.$appid.'&corpsecret='.$appsecret);
		if ($result)
		{
			$json = json_decode($result,true);
			if (!$json || isset($json['errcode'])) {
				$this->errCode = $json['errcode'];
				$this->errMsg = $json['errmsg'];
				return false;
			}
			$this->access_token = $json['access_token'];
			$expire = $json['expires_in'] ? intval($json['expires_in'])-100 : 3600;
			S($authname,$this->access_token,$expire);
			return $this->access_token;
		}
		return false;
	}

	/**
	 * 删除验证数据
	 * @param string $appid
	 */
	public function resetAuth($appid=''){
		if (!$appid) $appid = $this->appid;
		$this->access_token = '';
		//TODO: remove cache
		return true;
	}

	/**
	 * 创建菜单
	 * @param array $data 菜单数组数据
	 * example:
     * 	array (
     * 	    'button' => array (
     * 	      0 => array (
     * 	        'name' => '扫码',
     * 	        'sub_button' => array (
     * 	            0 => array (
     * 	              'type' => 'scancode_waitmsg',
     * 	              'name' => '扫码带提示',
     * 	              'key' => 'rselfmenu_0_0',
     * 	              'sub_button' => ''
     * 	            ),
     * 	            1 => array (
     * 	              'type' => 'scancode_push',
     * 	              'name' => '扫码推事件',
     * 	              'key' => 'rselfmenu_0_1',
     * 	              'sub_button' => ''
     * 	            ),
     * 	        ),
     * 	      ),
     * 	      1 => array (
     * 	        'name' => '发图',
     * 	        'sub_button' => array (
     * 	            0 => array (
     * 	              'type' => 'pic_sysphoto',
     * 	              'name' => '系统拍照发图',
     * 	              'key' => 'rselfmenu_1_0',
     * 	              'sub_button' => ''
     * 	            ),
     * 	            1 => array (
     * 	              'type' => 'pic_photo_or_album',
     * 	              'name' => '拍照或者相册发图',
     * 	              'key' => 'rselfmenu_1_1',
     * 	              'sub_button' => ''
     * 	            )
     * 	        ),
     * 	      ),
     * 	      2 => array (
     * 	        'type' => 'location_select',
     * 	        'name' => '发送位置',
     * 	        'key' => 'rselfmenu_2_0',
     * 	        'sub_button' => ''
     * 	      ),
     * 	    ),
     * 	)
     * type可以选择为以下几种，只是目前企业号3-8的类型不能收到事件消息，但可以达到效果。
     * 1、click：点击推事件
     * 2、view：跳转URL
     * 3、scancode_push：扫码推事件
     * 4、scancode_waitmsg：扫码推事件且弹出“消息接收中”提示框
     * 5、pic_sysphoto：弹出系统拍照发图
     * 6、pic_photo_or_album：弹出拍照或者相册发图
     * 7、pic_weixin：弹出微信相册发图器
     * 8、location_select：弹出地理位置选择器
	 */
	public function createMenu($data,$agentid=''){
	    if ($agentid=='') {
	    	$agentid=$this->agentid;
	    }
		if (!$this->access_token && !$this->checkAuth()) return false;
		$result = $this->http_post(self::API_URL_PREFIX.self::MENU_CREATE_URL.'access_token='.$this->access_token.'&agentid='.$agentid,self::json_encode($data));
		if ($result)
		{
			$json = json_decode($result,true);
			if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
				$this->errCode = $json['errcode'];
				$this->errMsg = $json['errmsg'];
				return false;
			}
			return true;
		}
		return false;
	}
	
	/**
	 * 获取菜单
	 * @return array('menu'=>array(....s))
	 */
	public function getMenu($agentid=''){
	    if ($agentid=='') {
	    	$agentid=$this->agentid;
	    }
		if (!$this->access_token && !$this->checkAuth()) return false;
		$result = $this->http_get(self::API_URL_PREFIX.self::MENU_GET_URL.'access_token='.$this->access_token.'&agentid='.$agentid);
		if ($result)
		{
			$json = json_decode($result,true);
			if (!$json || isset($json['errcode']) || $json['errcode']!=0) {
				$this->errCode = $json['errcode'];
				$this->errMsg = $json['errmsg'];
				return false;
			}
			return $json;
		}
		return false;
	}
	
	/**
	 * 删除菜单
	 * @return boolean
	 */
	public function deleteMenu($agentid=''){
	    if ($agentid=='') {
	    	$agentid=$this->agentid;
	    }
		if (!$this->access_token && !$this->checkAuth()) return false;
		$result = $this->http_get(self::API_URL_PREFIX.self::MENU_DELETE_URL.'access_token='.$this->access_token.'&agentid='.$agentid);
		if ($result)
		{
			$json = json_decode($result,true);
			if (!$json || !empty($json['errcode'])) {
				$this->errCode = $json['errcode'];
				$this->errMsg = $json['errmsg'];
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * 上传多媒体文件 (只有三天的有效期，过期自动被删除)
	 * 注意：数组的键值任意，但文件名前必须加@，使用单引号以避免本地路径斜杠被转义
	 * @param array $data {"media":'@Path\filename.jpg'}
	 * @param type 媒体文件类型:图片（image）、语音（voice）、视频（video），普通文件(file)
	 * @return boolean|array
	 * {
	 *    "type": "image",
	 *    "media_id": "0000001",
	 *    "created_at": "1380000000"
	 * }
	 */
	public function uploadMedia($data, $type){
		if (!$this->access_token && !$this->checkAuth()) return false;
		$result = $this->http_post(self::API_URL_PREFIX.self::MEDIA_UPLOAD.'access_token='.$this->access_token.'&type='.$type,$data);
		if ($result)
		{
			$json = json_decode($result,true);
			if (!$json || !empty($json['errcode'])) {
				$this->errCode = $json['errcode'];
				$this->errMsg = $json['errmsg'];
				return false;
			}
			return $json;
		}
		return false;
	}
	
	/**
	 * 根据媒体文件ID获取媒体文件
	 * @param string $media_id 媒体文件id
	 * @return raw data
	 */
	public function getMedia($media_id){
		if (!$this->access_token && !$this->checkAuth()) return false;
		$result = $this->http_get(self::API_URL_PREFIX.self::MEDIA_GET_URL.'access_token='.$this->access_token.'&media_id='.$media_id);
		if ($result)
		{
			$json = json_decode($result,true);
			if (isset($json['errcode'])) {
				$this->errCode = $json['errcode'];
				$this->errMsg = $json['errmsg'];
				return false;
			}
			return $result;
		}
		return false;
	}

	/**
	 * 创建部门
	 * @param array $data 	结构体为:
	 * array (
	 *     "name" => "邮箱产品组",   //部门名称
	 *     "parentid" => "1"         //父部门id
	 * )
	 * @return boolean|array
	 * 成功返回结果
	 * {
 	 *   "errcode": 0,        //返回码
	 *   "errmsg": "created",  //对返回码的文本描述内容
 	 *   "id": 2               //创建的部门id。 
	 * }
	 */
	public function createDepartment($data){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_post(self::API_URL_PREFIX.self::DEPARTMENT_CREATE_URL.'access_token='.$this->access_token,self::json_encode($data));
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	

	/**
	 * 更新部门
	 * @param array $data 	结构体为:
	 * array(
	 *     "id" => "1"               //部门id
	 *     "name" =>  "邮箱产品组",   //部门名称
	 * )
	 * @return boolean|array 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "updated"  //对返回码的文本描述内容
	 * }
	 */
	public function updateDepartment($data){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_post(self::API_URL_PREFIX.self::DEPARTMENT_UPDATE_URL.'access_token='.$this->access_token,self::json_encode($data));
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}

	/**
	 * 删除部门
	 * @param $id
	 * @return boolean|array 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "deleted"  //对返回码的文本描述内容
	 * }
	 */
	public function deleteDepartment($id){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_get(self::API_URL_PREFIX.self::DEPARTMENT_DELETE_URL.'access_token='.$this->access_token.'&id='.$id);
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * 获取部门列表
	 * @return boolean|array	 成功返回结果
	 * {
	 *    "errcode": 0,
	 *    "errmsg": "ok",
	 *    "department": [
	 *        {
	 *            "id": 1,
	 *            "name": "广州研发中心",
	 *            "parentid": 0
	 *        },
 	 *       {
	 *          "id": 2
  	 *          "name": "邮箱产品部",
  	 *          "parentid": 1
 	 *       }
	 *    ]
	 * }
	 */
	public function getDepartment(){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_get(self::API_URL_PREFIX.self::DEPARTMENT_LIST_URL.'access_token='.$this->access_token);
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode'])) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}

	/**
	 * 创建成员
	 * @param array $data 	结构体为:
     * array(
     *    "userid" => "zhangsan",
     *    "name" => "张三",
     *    "department" => [1, 2],
     *    "position" => "产品经理",
     *    "mobile" => "15913215421",
     *    "gender" => 1,     //性别。gender=0表示男，=1表示女
     *    "tel" => "62394",
     *    "email" => "zhangsan@gzdev.com",
     *    "weixinid" => "zhangsan4dev"
     * )
	 * @return boolean|array
	 * 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "created",  //对返回码的文本描述内容
	 * }
	 */
	public function createUser($data){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_post(self::API_URL_PREFIX.self::USER_CREATE_URL.'access_token='.$this->access_token,self::json_encode($data));
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	
	/**
	 * 更新成员
	 * @param array $data 	结构体为:
     * array(
     *    "userid" => "zhangsan",
     *    "name" => "张三",
     *    "department" => [1, 2],
     *    "position" => "产品经理",
     *    "mobile" => "15913215421",
     *    "gender" => 1,     //性别。gender=0表示男，=1表示女
     *    "tel" => "62394",
     *    "email" => "zhangsan@gzdev.com",
     *    "weixinid" => "zhangsan4dev"
     * )
	 * @return boolean|array 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "updated"  //对返回码的文本描述内容
	 * }
	 */
	public function updateUser($data){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_post(self::API_URL_PREFIX.self::USER_UPDATE_URL.'access_token='.$this->access_token,self::json_encode($data));
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * 删除成员
	 * @param $userid  员工UserID。对应管理端的帐号
	 * @return boolean|array 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "deleted"  //对返回码的文本描述内容
	 * }
	 */
	public function deleteUser($userid){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_get(self::API_URL_PREFIX.self::USER_DELETE_URL.'access_token='.$this->access_token.'&userid='.$userid);
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * 获取成员信息
	 * @param $userid  员工UserID。对应管理端的帐号
	 * @return boolean|array	 成功返回结果
	 * {
	 *    "errcode": 0,
	 *    "errmsg": "ok",
	 *    "userid": "zhangsan",
	 *    "name": "李四",
	 *    "department": [1, 2],
	 *    "position": "后台工程师",
	 *    "mobile": "15913215421",
	 *    "gender": 1,     //性别。gender=0表示男，=1表示女
	 *    "tel": "62394",
	 *    "email": "zhangsan@gzdev.com",
	 *    "weixinid": "lisifordev",        //微信号
	 *    "avatar": "http://wx.qlogo.cn/mmopen/ajNVdqHZLLA3W..../0",   //头像url。注：如果要获取小图将url最后的"/0"改成"/64"即可
	 *    "status": 1      //关注状态: 1=已关注，2=已冻结，4=未关注
	 * }
	 */
	public function getUserInfo($userid){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_get(self::API_URL_PREFIX.self::USER_GET_URL.'access_token='.$this->access_token.'&userid='.$userid);
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * 获取部门成员
	 * @param $department_id   部门id
	 * @param $fetch_child     1/0：是否递归获取子部门下面的成员
	 * @param $status          0获取全部员工，1获取已关注成员列表，2获取禁用成员列表，4获取未关注成员列表。status可叠加
	 * @return boolean|array	 成功返回结果
	 * {
	 *    "errcode": 0,
	 *    "errmsg": "ok",
	 *    "userlist": [
	 *            {
	 *                   "userid": "zhangsan",
	 *                   "name": "李四"
	 *            }
	 *      ]
	 * }
	 */
	public function getUserList($department_id,$fetch_child=0,$status=0){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_get(self::API_URL_PREFIX.self::USER_LIST_URL.'access_token='.$this->access_token
	            .'&department_id='.$department_id.'&fetch_child'.$fetch_child.'&status='.$status);
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}

	/**
	 * 创建标签
	 * @param array $data 	结构体为:
	 * array(
	 *    "tagname" => "UI"
	 * )
	 * @return boolean|array
	 * 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "created",  //对返回码的文本描述内容
	 *   "tagid": "1"
	 * }
	 */
	public function createTag($data){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_post(self::API_URL_PREFIX.self::TAG_CREATE_URL.'access_token='.$this->access_token,self::json_encode($data));
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * 更新标签
	 * @param array $data 	结构体为:
	 * array(
	 *    "tagid" => "1",
	 *    "tagname" => "UI design"
	 * )
	 * @return boolean|array 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "updated"  //对返回码的文本描述内容
	 * }
	 */
	public function updateTag($data){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_post(self::API_URL_PREFIX.self::TAG_UPDATE_URL.'access_token='.$this->access_token,self::json_encode($data));
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * 删除标签
	 * @param $tagid  标签TagID
	 * @return boolean|array 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "deleted"  //对返回码的文本描述内容
	 * }
	 */
	public function deleteTag($tagid){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_get(self::API_URL_PREFIX.self::TAG_DELETE_URL.'access_token='.$this->access_token.'&tagid='.$tagid);
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * 获取标签成员
	 * @param $tagid  标签TagID
	 * @return boolean|array	 成功返回结果
	 * {
	 *    "errcode": 0,
	 *    "errmsg": "ok",
	 *    "userlist": [
	 *          {
	 *              "userid": "zhangsan",
	 *              "name": "李四"
	 *          }
	 *      ]
	 * }
	 */
	public function getTag($tagid){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_get(self::API_URL_PREFIX.self::TAG_GET_URL.'access_token='.$this->access_token.'&tagid='.$tagid);
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * 增加标签成员
	 * @param array $data 	结构体为:
	 * array (
	 *    "tagid" => "1",
	 *    "userlist" => array(    //企业员工ID列表
	 *         "user1",
	 *         "user2"
	 *     )
	 * )
	 * @return boolean|array
	 * 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "ok",  //对返回码的文本描述内容
	 *   "invalidlist"："usr1|usr2|usr"     //若部分userid非法，则会有此段。不在权限内的员工ID列表，以“|”分隔
	 * }
	 */
	public function addTagUser($data){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_post(self::API_URL_PREFIX.self::TAG_ADDUSER_URL.'access_token='.$this->access_token,self::json_encode($data));
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * 删除标签成员
	 * @param array $data 	结构体为:
	 * array (
	 *    "tagid" => "1",
	 *    "userlist" => array(    //企业员工ID列表
	 *         "user1",
	 *         "user2"
	 *     )
	 * )
	 * @return boolean|array
	 * 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "deleted",  //对返回码的文本描述内容
	 *   "invalidlist"："usr1|usr2|usr"     //若部分userid非法，则会有此段。不在权限内的员工ID列表，以“|”分隔
	 * }
	 */
	public function delTagUser($data){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_post(self::API_URL_PREFIX.self::TAG_DELUSER_URL.'access_token='.$this->access_token,self::json_encode($data));
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}

	/**
	 * 主动发送信息接口
	 * @param array $data 	结构体为:
	 * array(
	 *         "touser" => "UserID1|UserID2|UserID3",
	 *         "toparty" => "PartyID1|PartyID2 ",
	 *         "totag" => "TagID1|TagID2 ",
	 *         "safe":"0"			//是否为保密消息，对于news无效
	 *         "agentid" => "001",	//应用id
	 *         "msgtype" => "text",  //根据信息类型，选择下面对应的信息结构体
	 * 
	 *         "text" => array(
	 *                 "content" => "Holiday Request For Pony(http://xxxxx)"
	 *         ),
	 * 
	 *         "image" => array(
	 *                 "media_id" => "MEDIA_ID"
	 *         ),
	 * 
	 *         "voice" => array(
	 *                 "media_id" => "MEDIA_ID"
	 *         ),
	 * 
	 *         " video" => array(
	 *                 "media_id" => "MEDIA_ID",
	 *                 "title" => "Title",
	 *                 "description" => "Description"
	 *         ),
	 * 
	 *         "file" => array(
	 *                 "media_id" => "MEDIA_ID"
	 *         ),
	 * 
	 *         "news" => array(			//不支持保密
	 *                 "articles":[
	 *                 array(
	 *                         "title" => "Title",
	 *                         "description" => "Description",
	 *                         "url" => "URL",
	 *                         "picurl" => "PIC_URL",
	 *                 ),
	 *                 array(
	 *                         "title" => "Title",
	 *                         "description" => "Description",
	 *                         "url" => "URL",
	 *                         "picurl" => "PIC_URL",
	 *                 )
	 *                 ]
	 *         ),
	 * 
	 *         "mpnews" => array(
	 *                 "articles":[
	 *                 array(
	 *                         "thumb_media_id" => "id",
	 *                         "author" => "Author",
	 *                         "content_source_url" => "URL",
	 *                         "content" => "Content"
	 *                         "digest" => "Digest description",
	 *                         "show_cover_pic" => "0"
	 *                 ),
	 *                 array(
	 *                         "thumb_media_id" => "id",
	 *                         "author" => "Author",
	 *                         "content_source_url" => "URL",
	 *                         "content" => "Content"
	 *                         "digest" => "Digest description",
	 *                         "show_cover_pic" => "0"
	 *                 )
	 *                 ]
	 *         )
	 * )
	 * 请查看官方开发文档中的 发送消息 -> 消息类型及数据格式
	 * 
	 * @return boolean|array
	 * 如果对应用或收件人、部门、标签任何一个无权限，则本次发送失败；
	 * 如果收件人、部门或标签不存在，发送仍然执行，但返回无效的部分。
	 * {
	 *    "errcode": 0,
	 *    "errmsg": "ok",
	 *    "invaliduser": "UserID1",
	 *    "invalidparty":"PartyID1",
	 *    "invalidtag":"TagID1"
	 * }
	 */
	public function sendMessage($data){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_post(self::API_URL_PREFIX.self::MASS_SEND_URL.'access_token='.$this->access_token,self::json_encode($data));
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}

	/**
	 * 二次验证
	 * 企业在开启二次验证时，必须填写企业二次验证页面的url。
	 * 当员工绑定通讯录中的帐号后，会收到一条图文消息，
	 * 引导员工到企业的验证页面验证身份，企业在员工验证成功后，
	 * 调用如下接口即可让员工关注成功。
	 * 
	 * @param $userid
	 * @return boolean|array 成功返回结果
	 * {
	 *   "errcode": 0,        //返回码
	 *   "errmsg": "ok"  //对返回码的文本描述内容
	 * }
	 */
	public function authSucc($userid){
	    if (!$this->access_token && !$this->checkAuth()) return false;
	    $result = $this->http_get(self::API_URL_PREFIX.self::AUTHSUCC_URL.'access_token='.$this->access_token.'&userid='.$userid);
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode']) || $json['errcode']!=0) {
	            $this->errCode = $json['errcode'];
	            $this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json;
	    }
	    return false;
	}
	
	/**
	 * oauth 授权跳转接口
	 * @param string $callback 回调URI
	 * @param string $state 重定向后会带上state参数，企业可以填写a-zA-Z0-9的参数值
	 * @return string
	 */
	public function getOauthRedirect($callback,$state='STATE',$scope='snsapi_base'){
	    return self::OAUTH_PREFIX.self::OAUTH_AUTHORIZE_URL.'appid='.$this->appid.'&redirect_uri='.urlencode($callback).'&response_type=code&scope='.$scope.'&state='.$state.'#wechat_redirect';
	}

}



/**
 * PKCS7Encoder class
 *
 * 提供基于PKCS7算法的加解密接口.
 */
class PKCS7Encoder
{
    public static $block_size = 32;

    /**
     * 对需要加密的明文进行填充补位
     * @param $text 需要进行填充补位操作的明文
     * @return 补齐明文字符串
     */
    function encode($text)
    {
        $block_size = PKCS7Encoder::$block_size;
        $text_length = strlen($text);
        //计算需要填充的位数
        $amount_to_pad = PKCS7Encoder::$block_size - ($text_length % PKCS7Encoder::$block_size);
        if ($amount_to_pad == 0) {
            $amount_to_pad = PKCS7Encoder::block_size;
        }
        //获得补位所用的字符
        $pad_chr = chr($amount_to_pad);
        $tmp = "";
        for ($index = 0; $index < $amount_to_pad; $index++) {
            $tmp .= $pad_chr;
        }
        return $text . $tmp;
    }

    /**
     * 对解密后的明文进行补位删除
     * @param decrypted 解密后的明文
     * @return 删除填充补位后的明文
     */
    function decode($text)
    {

        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            $pad = 0;
        }
        return substr($text, 0, (strlen($text) - $pad));
    }

}

/**
 * Prpcrypt class
 *
 * 提供接收和推送给公众平台消息的加解密接口.
 */
class Prpcrypt
{
    public $key;

    function Prpcrypt($k)
    {
        $this->key = base64_decode($k . "=");
    }

    /**
     * 对明文进行加密
     * @param string $text 需要加密的明文
     * @return string 加密后的密文
     */
    public function encrypt($text, $appid)
    {

        try {
            //获得16位随机字符串，填充到明文之前
            $random = "aaaabbbbccccdddd"; //$this->getRandomStr();
            $text = $random . pack("N", strlen($text)) . $text . $appid;
            // 网络字节序
            $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
            $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->key, 0, 16);
            //使用自定义的填充方式对明文进行补位填充
            $pkc_encoder = new PKCS7Encoder;
            $text = $pkc_encoder->encode($text);
            mcrypt_generic_init($module, $this->key, $iv);
            //加密
            $encrypted = mcrypt_generic($module, $text);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module);

            //			print(base64_encode($encrypted));
            //使用BASE64对加密后的字符串进行编码
            return array(ErrorCode::$OK, base64_encode($encrypted));
        } catch (Exception $e) {
            print $e;
            return array(ErrorCode::$EncryptAESError, null);
        }
    }

    /**
     * 对密文进行解密
     * @param string $encrypted 需要解密的密文
     * @return string 解密得到的明文
     */
    public function decrypt($encrypted, $appid)
    {

        try {
            //使用BASE64对需要解密的字符串进行解码
            $ciphertext_dec = base64_decode($encrypted);
            $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->key, 0, 16);
            mcrypt_generic_init($module, $this->key, $iv);
            //解密
            $decrypted = mdecrypt_generic($module, $ciphertext_dec);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module);
        } catch (Exception $e) {
            return array(ErrorCode::$DecryptAESError, null);
        }


        try {
            //去除补位字符
            $pkc_encoder = new PKCS7Encoder;
            $result = $pkc_encoder->decode($decrypted);
            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16)
                return "";
            $content = substr($result, 16, strlen($result));
            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
            $from_appid = substr($content, $xml_len + 4);
        } catch (Exception $e) {
            print $e;
            return array(ErrorCode::$IllegalBuffer, null);
        }
        if ($from_appid != $appid)
            return array(ErrorCode::$ValidateAppidError, null);
        return array(0, $xml_content);

    }


    /**
     * 随机生成16位字符串
     * @return string 生成的字符串
     */
    function getRandomStr()
    {

        $str = "";
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < 16; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }
        return $str;
    }

}

/**
 * error code
 * 仅用作类内部使用，不用于官方API接口的errCode码
 */
class ErrorCode
{
    public static $OK = 0;
    public static $ValidateSignatureError = 40001;
    public static $ParseXmlError = 40002;
    public static $ComputeSignatureError = 40003;
    public static $IllegalAesKey = 40004;
    public static $ValidateAppidError = 40005;
    public static $EncryptAESError = 40006;
    public static $DecryptAESError = 40007;
    public static $IllegalBuffer = 40008;
    public static $EncodeBase64Error = 40009;
    public static $DecodeBase64Error = 40010;
    public static $GenReturnXmlError = 40011;
    public static $errCode=array(
            '0'=>'无问题',
            '40001'=>'签名验证错误',
            '40002'=>'xml解析失败',
            '40003'=>'sha加密生成签名失败',
            '40004'=>'encodingAesKey 非法',
            '40005'=>'appid 校验错误',
            '40006'=>'aes 加密失败',
            '40007'=>'aes 解密失败',
            '40008'=>'解密后得到的buffer非法',
            '40009'=>'base64加密失败',
            '40010'=>'base64解密失败',
            '40011'=>'生成xml失败',
    );
    public static function getErrText($err) {
        if (isset(self::$errCode[$err])) {
            return self::$errCode[$err];
        }else {
            return false;
        };
    }
}
