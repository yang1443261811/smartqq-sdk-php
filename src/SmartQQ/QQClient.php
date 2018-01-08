<?php
/**
 * Created by kilingzhang.com
 * User: kilingzhang
 * Date: 18-1-5
 * Time: 下午12:49
 */

namespace kilingzhang\SmartQQ;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use kilingzhang\SmartQQ\Entity\Message;
use kilingzhang\SmartQQ\Exception\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use kilingzhang\SmartQQ\Entity\ClientToken;
use kilingzhang\SmartQQ\Utils\EncryptUtils;
use kilingzhang\SmartQQ\Utils\MessageUtils;
use kilingzhang\SmartQQ\Utils\Utils;


class QQClient
{
    // 66 二维码未失效
    // 0 登陆成功
    // 67 认证中
    // 65 失效
    const OK_STATUS = 0;
    const WAITING_STATUS = 65;
    const ASKING_STATUS = 67;
    const FAILURE_STATUS = 65;


    private $client;
    private $qrcodePath;
    private $checkSigUrl;


    private $ptqrtoken;
    private $uin;
    private $skey;
    private $bkn;
    private $clientid = 53999199;
    private $vfwebqq;
    private $ptwebqq;
    private $psessionid;
    private $hash;
    private $supertoken;
    private $superkey;

    private $jarArray;
    private $jar;

    public function __construct(ClientToken $clientToken = null)
    {
        if ($clientToken != null) {
            $this->setClienToken($clientToken);
        }

        $this->client = new Client(['cookies' => true]);

        $this->qrcodePath = './qrcode.png';

    }

    public function CookieJartoArray()
    {
        $cookieJar = $this->client->getConfig('cookies');
        return $cookieJar->toArray();
    }

    public static function ArraytoCookieJar(array $cookies)
    {
        $jar = new CookieJar();
        foreach ($cookies as $value) {
            $cookie = new SetCookie();
            $cookie->setName($value['Name']);
            $cookie->setValue($value['Value']);
            $cookie->setDomain($value['Domain']);
            $cookie->setExpires($value['Expires']);
            $cookie->setPath($value['Path']);
            $jar->setCookie($cookie);
        }
        return $jar;
    }

    /**
     *
     * @param ResponseInterface $response
     * @return array
     */
    public function getCookies(ResponseInterface $response): array
    {
        $cookie = $response->getHeader('set-cookie');
        foreach ($cookie as $value) {
            preg_match('/(.*?)=(.*?);/', $value, $pre);
            $cookies[$pre[1]] = $pre[2];
        }
        return $cookies;
    }

    /**
     *
     * @return ClientToken
     */
    public function getClienToken(): ClientToken
    {
        $clienToken = new ClientToken();
        $clienToken->setUin($this->uin);
        $clienToken->setCheckSigUrl($this->checkSigUrl);
        $clienToken->setPtqrtoken($this->ptqrtoken);
        $clienToken->setQrcodePath($this->qrcodePath);

        $clienToken->setVfwebqq($this->vfwebqq);
        $clienToken->setClientid($this->clientid);
        $clienToken->setPsessionid($this->psessionid);
        $clienToken->setSkey($this->skey);
        $clienToken->setBkn($this->bkn);
        $clienToken->setPtwebqq($this->ptwebqq);
        $clienToken->setHash($this->hash);
        //CookieJarArray
        $clienToken->setJarArray($this->jarArray);
        return $clienToken;
    }

    /**
     *
     * @param ClientToken $clientToken
     */
    public function setClienToken(ClientToken $clientToken): void
    {
        $this->uin = $clientToken->getUin();
        $this->checkSigUrl = $clientToken->getCheckSigUrl();
        $this->ptqrtoken = $clientToken->getPtqrtoken();
        $this->qrcodePath = $clientToken->getQrcodePath();

        $this->vfwebqq = $clientToken->getVfwebqq();
        $this->clientid = $clientToken->getClientid();
        $this->psessionid = $clientToken->getPsessionid();
        $this->skey = $clientToken->getSkey();
        $this->bkn = $clientToken->getBkn();
        $this->ptwebqq = $clientToken->getPtwebqq();
        $this->hash = $clientToken->getHash();
        //CookieJarArray
        $this->jarArray = $clientToken->getJarArray();
        $this->jar = self::ArraytoCookieJar($this->jarArray);
    }

    public function setQRCodePath(string $path = './qrcode.png'): string
    {
        return $this->qrcodePath = $path;
    }

    /**
     *
     * @return string
     */
    public function getQRCodePath(): string
    {
        return $this->qrcodePath;
    }

    /**
     *
     * @return int
     */
    public function refreshQRCode(): int
    {
        $response = $this->client->get(URL::qrcodeURL, [
            't' => time()
        ]);
        $qrcode = $response->getBody();
        $cookies = $this->getCookies($response);
        $this->ptqrtoken = EncryptUtils::hash33($cookies['qrsig']);
        return file_put_contents($this->qrcodePath, $qrcode);
    }

    /**
     *
     * @return int
     */
    public function verifyQrCodeStatus(): int
    {
        $response = $this->client->get(URL::ptqrloginURL . "&ptqrtoken={$this->ptqrtoken}&action=0-0-" . time());
        $ptuiCB = MessageUtils::ptuiCBToArray($response->getBody());
        $code = $ptuiCB['code'];
        if ($code == self::OK_STATUS) {
            $this->checkSigUrl = $ptuiCB['link'];
//            $cookies = $this->getCookies($response);
//            $this->supertoken = $cookies['supertoken'];
//            $this->superkey = $cookies['superkey'];
        }
        return $code;
    }

    /**
     *
     * @return bool
     */
    public function QRLogin(): bool
    {
        set_time_limit(0);
        while (true) {
            $code = $this->verifyQrCodeStatus();
            if ($code == self::OK_STATUS) {
                return true;
            } elseif ($code == self::FAILURE_STATUS) {
                $this->refreshQRCode();
            }
        }
        return false;
    }

    /**
     *
     * @return string
     */
    public function getVfwebqq(): string
    {
        $options['headers'] = [
            'Referer' => 'http://s.web2.qq.com/proxy.html?v=20130916001&callback=1&id=1'
        ];
        $response = $this->client->get(URL::getvfwebqqURL . "&t=" . Utils::getMillisecond(), $options);
        return $this->vfwebqq = MessageUtils::getVfwebqq($response->getBody());
    }

    public function getHash(): string
    {
        return EncryptUtils::hashUin(substr($this->uin, 1, strlen($this->uin) - 1), $this->ptwebqq);
    }

    /**
     *
     * @return string
     */
    public function getPsessionid(): string
    {
        $options['headers'] = [
            'Referer' => 'http://d1.web2.qq.com/proxy.html?v=20151105001&callback=1&id=2',
        ];
        $options['form_params'] = [
            'r' => '{"ptwebqq":"","clientid":53999199,"psessionid":"","status":"online"}'
        ];
        $response = $this->client->post(URL::login2URL, $options);
        return $this->psessionid = MessageUtils::getPsessionid($response->getBody());
    }

    /**
     *
     * @return ClientToken
     */
    public function Login(): ClientToken
    {
        $response = $this->client->get($this->checkSigUrl, [
            'allow_redirects' => false
        ]);
        $cookies = $this->getCookies($response);
        $this->ptwebqq = array_key_exists('ptwebqq', $cookies) ? $cookies['ptwebqq'] : '';
        $this->uin = array_key_exists('uin', $cookies) ? $cookies['uin'] : '';
        $this->skey = array_key_exists('skey', $cookies) ? $cookies['skey'] : '';
        $this->bkn = EncryptUtils::getBkn($this->bkn);
        $this->hash = $this->getHash();
        $this->getVfwebqq();
        $this->getPsessionid();

        $this->jarArray = $this->CookieJartoArray();
        $this->jar = self::ArraytoCookieJar($this->jarArray);

        return $this->getClienToken();
    }



    public function getFriendsList()
    {
        $options['headers'] = [
            'Referer' => 'http://s.web2.qq.com/proxy.html?v=20130916001&callback=1&id=1',
        ];
        $options['form_params'] = [
            'r' => '{"vfwebqq":"' . $this->vfwebqq . '","hash":"' . $this->hash . '"}'
        ];
        $options['cookies'] = $this->jar;
        $response = $this->client->post(URL::getUserFriendsURL, $options);
        //TODO change echo to return
        echo $response->getBody();
    }

    public function getGroupNameList()
    {
        $options['headers'] = [
            'Referer' => 'http://s.web2.qq.com/proxy.html?v=20130916001&callback=1&id=1',
        ];
        $options['form_params'] = [
            'r' => '{"vfwebqq":"' . $this->vfwebqq . '","hash":"' . $this->hash . '"}'
        ];
        $options['cookies'] = $this->jar;
        $response = $this->client->post(URL::getGroupNameListURL, $options);
        //TODO change echo to return
        echo $response->getBody();
    }

    public function getDiscusList()
    {
        $options['headers'] = [
            'Referer' => 'http://s.web2.qq.com/proxy.html?v=20130916001&callback=1&id=1',
        ];
        $options['cookies'] = $this->jar;
        $response = $this->client->get(URL::getDiscusListURL . "?clientid=53999199&psessionid={$this->psessionid}&vfwebqq={$this->vfwebqq}&t=" . Utils::getMillisecond(), $options);
        //TODO change echo to return
        echo $response->getBody();
    }

    public function getOnlineBuddies()
    {
        $options['headers'] = [
            'Referer' => 'http://d1.web2.qq.com/proxy.html?v=20151105001&callback=1&id=2',
        ];
        $options['cookies'] = $this->jar;
        $response = $this->client->get(URL::getOnlineBuddiesURL . "?vfwebqq={$this->vfwebqq}&clientid=53999199&psessionid={$this->psessionid}&t=" . Utils::getMillisecond(), $options);
        //TODO change echo to return
        echo $response->getBody();
    }

    public function getRecentList()
    {
        $options['headers'] = [
            'Referer' => 'http://d1.web2.qq.com/proxy.html?v=20151105001&callback=1&id=2',
        ];
        $options['form_params'] = [
            'r' => '{"vfwebqq":"' . $this->vfwebqq . '","clientid":53999199,"psessionid":"' . $this->psessionid . '"}'
        ];
        $options['cookies'] = $this->jar;
        $response = $this->client->post(URL::getRecentListURL, $options);
        //TODO change echo to return
        echo $response->getBody();
    }

    public function getSelfInfo()
    {
        $options['headers'] = [
            'Referer' => 'http://s.web2.qq.com/proxy.html?v=20130916001&callback=1&id=1',
        ];
        $options['cookies'] = $this->jar;
        $response = $this->client->get(URL::getSelfInfoURL . "?t=" . Utils::getMillisecond(), $options);
        //TODO change echo to return
        echo $response->getBody();
    }

    public function pollMessage(PollMsgInterface $pollMsg){
        set_time_limit(0);
        $options['headers'] = [
            'Referer' => 'http://d1.web2.qq.com/proxy.html?v=20151105001&callback=1&id=2',
        ];
        $options['form_params'] = [
            'r' => '{"ptwebqq":"","clientid":53999199,"psessionid":"'. $this->psessionid .'","key":""}'
        ];
        $options['cookies'] = $this->jar;
        $response = $this->client->post(URL::pollURL, $options);
        //TODO change echo to return
        $responseMsg = $response->getBody();
        $message = new Message($responseMsg);
        if($message->retcode != 0){
            throw new InvalidArgumentException('login out...');
        }
        $pollType = $message->result->pollType;
        switch ($pollType){
            case 'message':
                $pollMsg->FreindMessage($responseMsg);
                break;
            case 'group_message':
                $pollMsg->GroupMessage($responseMsg);
                break;
            case 'discu_message':
                $pollMsg->DiscusMessage($responseMsg);
                break;

        }
    }

    public function test()
    {
//        $this->pollMessage(new PollMessageEvent());
        $responseMsg = '{"result":[{"poll_type":"message","value":{"content":[["font",{"color":"000000","name":"微软雅黑","size":10,"style":[0,0,0]}],"尴尬","😅","。。",["face",0],"123213123123 测试下 ",["face",0]," ","😅"],"from_uin":1353693508,"msg_id":30765,"msg_type":1,"time":1515399317,"to_uin":1508758325}}],"retcode":0}';
        $message = new Message();
        $message->setMsgObj($responseMsg);
        if($message->retcode != 0){
            throw new InvalidArgumentException('login out...');
        }
        $pollMsg = new PollMessageEvent();
        $pollType = $message->result[0]->poll_type;
        switch ($pollType){
            case 'message':
                $pollMsg->FreindMessage($message);
                break;
            case 'group_message':
                $pollMsg->GroupMessage($message);
                break;
            case 'discu_message':
                $pollMsg->DiscusMessage($message);
                break;

        }
    }

}