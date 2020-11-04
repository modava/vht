<?php

namespace modava\vht;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;

class SmsVht extends Component
{
    private $url = 'http://apiv2.incomsms.vn/MtService/SendSms';
    public $username;
    public $password;
    public $prefixId;
    public $commandCode;
    public $phones = [];
    public $messages = [];
    public $debug = false;
    private $requestId = 0;
    private $msgContentTypeId = 0;
    private $feeTypeId = 0;
    private $statusCode = [];
    private $error = [];

    public function send($phones = null, string $message = '')
    {
        $this->statusCode = [];
        $this->error = [];
        if ($phones === null) $phones = $this->phones;
        if (!is_array($phones)) $phones = [$phones];
        foreach ($phones as $phone) {
            $this->sendSms($phone, $message);
        }
        if ($this->debug === true) {
            $debug = [];
            foreach ($this->getStatusCode() as $phone => $statusCode) {
                $debug[] = $phone . ': ' . $this->getResponseMessage($statusCode) . ', Message: ' . $this->getMessage($phone);
            }
            \Yii::warning(json_encode($debug, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * @return bool
     */
    private function sendSms($phone = null, string $message = '')
    {
        if (!$this->checkData($phone, $message)) {
            return false;
        }
        if (substr($phone, 0, 1) == 0) $phone = '84' . substr($phone, 1);
        if ($message == '') {
            $message = $this->getMessage($phone);
        } else {
            if (is_string($this->messages) && trim($this->messages) != '') $this->messages = '';
            if (is_array($this->messages) && $phone != null && trim($phone) != '') {
                $this->messages[$phone] = $message;
            }
        }
        try {
            $client = new Client([
                'verify' => false,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
            $response = $client->request('POST', $this->url, [
                'form_params' => [
                    'Username' => $this->username,
                    'Password' => $this->password,
                    'PhoneNumber' => $phone,
                    'PrefixId' => $this->prefixId,
                    'CommandCode' => $this->commandCode,
                    'RequestId' => $this->requestId,
                    'MsgContent' => $message,
                    'MsgContentTypeId' => $this->msgContentTypeId,
                    'FeeTypeId' => $this->feeTypeId
                ]
            ]);
            if ($response->getStatusCode() !== 200) {
                $this->statusCode[$phone] = -2;
                return false;
            }
            $response = json_decode($response->getBody());
            $this->statusCode[$phone] = $response->StatusCode;
            return $response->StatusCode == 1;
        } catch (GuzzleException $ex) {
            $this->statusCode[$phone] = -2;
            $this->error[$phone] = $ex->getMessage();
            return false;
        }
    }

    private function getMessage($phone = null)
    {
        if (is_string($this->messages)) return $this->messages;
        if (!is_array($this->messages) || !array_key_exists($phone, $this->messages)) return null;
        return $this->messages[$phone];
    }

    public function checkData($phone, $message)
    {
        $array_data = [
            'username' => $this->username,
            'password' => $this->password,
            'prefixId' => $this->prefixId,
            'commandCode' => $this->commandCode,
            'phone' => $phone,
            'message' => $message
        ];
        if (in_array(null, $array_data) ||
            in_array('', $array_data)) {
            $this->statusCode[$phone] = -1;
            $arr_null = array_filter([
                'username' => $this->username,
                'password' => $this->password,
                'prefixId' => $this->prefixId,
                'commandCode' => $this->commandCode,
                'phone' => $phone,
                'message' => $message
            ], function ($var) {
                return $var == null || $var == '';
            });
            if (count($arr_null) > 0) {
                if($phone == '') $phone = null;
                if(!array_key_exists($phone, $this->error)) $this->error[$phone] = '';
                $this->error[$phone] .= 'Thiếu tham số: ' . implode(', ', array_keys($arr_null)) . '.';
            }
            return false;
        }
        return true;
    }

    public function getStatusCode(string $phone = '')
    {
        if ($phone != '') {
            if (substr($phone, 0, 1) == 0) $phone = '84' . substr($phone, 1);
            if (is_array($this->statusCode) && array_key_exists($phone, $this->statusCode)) return $this->statusCode[$phone];
            return null;
        }
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getResponseMessage($code = null, $phone = ''): string
    {

        $errors = [
            509 => 'Brandname chưa được khai báo',
            399 => 'MT của đối tác bị lặp',
            398 => 'Không tìm thấy đối tác',
            397 => 'Không tìm thấy nhà cung cấp',
            396 => 'Không tìm thấy phiên dịch vụ',
            395 => 'Địa chỉ IP không được đăng ký',
            394 => 'Đối tác không tìm thấy với User gửi',
            393 => 'Sai account gửi hoặc password gửi tin',
            392 => 'Không tìm thấy Telcos , số điện thoại bị sai',
            359 => 'Phiên không tồn tại hoặc chưa được kích hoạt',
            360 => 'Số điện thoại có trong danh sách từ chối nhận tin',
            357 => 'Dịch vụ không tồn tại hoặc chưa được kích hoạt',
            356 => 'Mã dịch vụ để trống',
            253 => 'Thêm mới vào bảng Concentrator bị sai',
            304 => 'MT gửi lặp;( cùng 1 nội dung gửi tới 1 số điện thoại trong thời gian ngắn)',
            511 => 'Chưa khai báo SessionPrefix',
            510 => 'Không được phép gửi MT chủ động',
            267 => 'Sai Username hoặc Password, hoặc IP không được phép gửi tin',
            515 => 'Độ dài vượt quá quy định của Telcos',
            530 => 'Từ khóa bị chặn bởi Telcos (Keyword was block by Telco)',
            535 => 'Số Điện thoại 11 số đã chuyển về 10 số',
            536 => 'Mẫu Template phải bắt đầu bằng [QC] hoặc (QC)',
            537 => 'Template chưa được khai báo',
            1 => 'Gửi thành công (Success)',
            -1 => 'Lỗi cấu hình',
            -2 => 'Gửi thất bại',
            null => '-'
        ];
        $statusCode = $code ?: $this->getStatusCode($phone);
        if (!is_numeric($statusCode)) $statusCode = null;
        if (!array_key_exists($statusCode, $errors)) {
            $statusCode = -1;
            if ($code == null && $phone != '') $this->statusCode[$phone] = $statusCode;
        }
        return $errors[$statusCode] . (array_key_exists($phone, $this->error) && $this->error[$phone] != null ? ': ' . $this->error[$phone] : '');
    }
}