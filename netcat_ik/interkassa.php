<?php
class nc_payment_system_interkassa extends nc_payment_system {

    const TARGET_URL = "https://sci.interkassa.com/";

    protected $automatic = TRUE;

    // принимаемые валюты
    protected $accepted_currencies = array('EUR', 'USD', 'UAH', 'RUR', 'RUB', 'BYR', 'XAU');
    protected $currency_map = array(
        'EUR' => 'EUR',
        'USD' => 'USD',
        'UAH' => 'UAH',
        'RUR' => 'RUB',
        'RUB' => 'RUB',
        'BYR' => 'BYR',
        'XAU' => 'XAU'
    );

    // параметры сайта в платежной системе
    protected $settings = array(
        'test_mode' => null,
        'id_cashbox' => null,
        'secret_key' => null,
        'test_key' => null,
        'api_enable' => null,
        'api_id' => null,
        'api_key' => null,
        'PaymentSuccessPage' => null,
        'PaymentFailedPage' => null,
        'PaymentPendingPage' => null,
    );

    /*
     * SQL request add paysystem to list paysystems
        INSERT INTO  `Classificator_PaymentSystem` (`PaymentSystem_ID` ,`PaymentSystem_Name` ,`PaymentSystem_Priority` ,`Value` ,`Checked`)
        VALUES (NULL ,  'Interkassa',  '1',  'nc_payment_system_interkassa',  '1');
    */

    public $api_id;
    public $api_key;
    public $id_cashbox;
    public $secret_key;

    public function execute_payment_request(nc_payment_invoice $invoice) {

        $this -> api_id = $this->get_setting('api_id');
        $this -> api_key = $this->get_setting('api_key');
        $this -> id_cashbox = $this->get_setting('id_cashbox');
        $this -> secret_key = $this->get_setting('secret_key');

        $amount = $invoice->get_amount("%0.2F");
        $currency = ($invoice->get_currency() == 'RUR') ? 'RUB' : $invoice->get_currency();

        $FormData = array();
        $FormData['ik_am'] = $amount;
        $FormData['ik_cur'] = $currency;
        $FormData['ik_pm_no'] = $invoice->get_id();
        $FormData['ik_co_id'] = $this->get_setting('id_cashbox');
        $FormData['ik_desc'] = '#' . $invoice->get_id();
        $FormData['ik_desc'] = 'Payment for order #' . $invoice->get_id();
        $FormData['ik_suc_u'] = empty($this->get_setting('PaymentSuccessPage'))? nc_core('catalogue')->get_current('Domain')
            : $this->get_setting('PaymentSuccessPage');
        $FormData['ik_fal_u'] = empty($this->get_setting('PaymentFailedPage'))? nc_core('catalogue')->get_current('Domain')
            : $this->get_setting('PaymentFailedPage');
        $FormData['ik_pnd_u'] = empty($this->get_setting('PaymentPendingPage'))? nc_core('catalogue')->get_current('Domain')
            : $this->get_setting('PaymentPendingPage');
        $FormData['ik_ia_u'] = $this->get_callback_script_url() . '&invoice=' . $invoice->get_id();

        if($this->get_setting('test_mode'))
            $FormData['ik_pw_via'] = 'test_interkassa_test_xts';

        $FormData['ik_sign'] = self::IkSignFormation($FormData, $this->get_setting('secret_key'));

        $url_request = $this->get_callback_script_url() . '&invoice=' . $invoice->get_id() . '&paysys';

        ob_end_clean();
        $nc_core = nc_core();
        include $nc_core->TEMPLATE_FOLDER . 'interkassa/payment.php';
        exit;
    }

    public function validate_payment_request_parameters() {
        if (!$this->get_setting('id_cashbox') && !$this->get_setting('secret_key')) {
            $this->add_error('ERROR!!! Requires params is not valid!');
        }
    }

    public function on_response(nc_payment_invoice $invoice = null) {

        if(count($_POST) && $this->checkIP() && $this->get_setting('id_cashbox') == $_POST['ik_co_id']){

            if(isset($_POST['ik_pw_via']) && $_POST['ik_pw_via'] == 'test_interkassa_test_xts')
                $secret_key = $this->get_setting('test_key');
            else
                $secret_key = $this->get_setting('secret_key');

            $request_sign = $_POST['ik_sign'];

            $sign = self::IkSignFormation($_POST, $secret_key);

            if($request_sign == $sign){
                switch ($_POST['ik_inv_st']) {
                    case 'success':
                        $this->on_payment_success($invoice);
                        header_remove();
                        header('Location: ' . $this->get_setting('PaymentSuccessPage'));
                        exit;
                    break;
                    case 'fail':
                    case 'canceled':
                        $this->on_payment_failure($invoice);
                        header_remove();
                        header('Location: ' . $this -> get_setting('PaymentFailedPage'));
                        exit;
                    break;
                }

                header_remove();
                header('Location: ' . $this->get_setting('PaymentPendingPage'));
                exit;
            }
            else{
                $this->on_payment_failure($invoice);
                header_remove();
                header('Location: ' . $this -> get_setting('PaymentFailedPage'));
                exit;
            }

        }else{
            die('params didnt match');
        }
    }

    public function validate_payment_callback_response(nc_payment_invoice $invoice = null) {

        if(empty($this->get_response_value('ik_pm_no')) && empty($this->get_response_value('ik_co_id')))
            $this->add_error('ERROR!!! Empty params is ik_pm_no, ik_co_id');

        $request = $_POST;
        if(isset($_GET['paysys'])) {
            if (isset($request['ik_act']) && $request['ik_act'] == 'process'){
                $request['ik_sign'] = self::IkSignFormation($request, $this->get_setting('secret_key'));
                $data = self::getAnswerFromAPI($request);
            }
            else
                $data = self::IkSignFormation($request, $this->get_setting('secret_key'));

            echo $data;
            exit;
        }
    }

    public function load_invoice_on_callback() {
        return $this->load_invoice($this->get_response_value('ik_pm_no'));
    }

    private static function IkSignFormation($data, $secret_key)
    {
        $dataSet = array();
        foreach ($data as $key => $value) {
            if (preg_match('/ik_/i', $key) && $key != 'ik_sign'){
                $dataSet[$key] = $value;
            }
        }

        ksort($dataSet, SORT_STRING);
        array_push($dataSet, $secret_key);

        $arg = implode(':', $dataSet);
        $ik_sign = base64_encode(md5($arg, true));

        return $ik_sign;
    }

    public static function getAnswerFromAPI($data)
    {
        $ch = curl_init('https://sci.interkassa.com/');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        return $result;
    }

    public function getPaymentSystems()
    {
        $username = $this -> api_id;
        $password = $this -> api_key;
        $remote_url = 'https://api.interkassa.com/v1/paysystem-input-payway?checkoutId=' . $this -> id_cashbox;

        // Create a stream
        $opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Authorization: Basic " . base64_encode("$username:$password")
            )
        );

        $context = stream_context_create($opts);
        $file = file_get_contents($remote_url, false, $context);
        $json_data = json_decode($file);

        if ($json_data->status != 'error') {
            $payment_systems = array();
            foreach ($json_data->data as $ps => $info) {
                $payment_system = $info->ser;
                if (!array_key_exists($payment_system, $payment_systems)) {
                    $payment_systems[$payment_system] = array();
                    foreach ($info->name as $name) {
                        if ($name->l == 'en') {
                            $payment_systems[$payment_system]['title'] = ucfirst($name->v);
                        }
                        $payment_systems[$payment_system]['name'][$name->l] = $name->v;

                    }
                }
                $payment_systems[$payment_system]['currency'][strtoupper($info->curAls)] = $info->als;

            }
            return $payment_systems;
        } else {
            return '<strong style="color:red;">API connection error!<br>' . $json_data->message . '</strong>';
        }
    }

    public function checkIP(){
        $ip_stack = array(
            'ip_begin'=>'151.80.190.97',
            'ip_end'=>'151.80.190.104'
        );

        $ip = ip2long($_SERVER['REMOTE_ADDR'])? ip2long($_SERVER['REMOTE_ADDR']) : !ip2long($_SERVER['REMOTE_ADDR']);

        if(($ip >= ip2long($ip_stack['ip_begin'])) && ($ip <= ip2long($ip_stack['ip_end']))){
            return true;
        }
        return false;
    }
}

