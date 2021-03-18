<?php
namespace App\Models;

use Exception;
use Illuminate\Support\Facades\Log;

class WebPay
{

  public $phone;
  public $email;
  public $paystack;

//  secret key
  public $sk;


  public function __construct()
  {
    $this->sk=env('SK');
  }

  public function authorize($phone)
  {
      $this->phone = $phone;
      $this->email = $phone . '@' . env('DOMAIN');
  }

  /**
  * Provides authorization url
  *
  * Returns a paystack object
  */
  public function pay($amount = 500)
  {
      $reference = sha1(md5($this->phone . time()));

      $result = array();
      //Set other parameters as keys in the $postdata array
      $postdata = array(
        'email' => $this->email,
        'amount' => $amount * 100,
        "reference" => $reference,
        "callback_url"=>url("fund/callback"), //callback url
      );
      $url = "https://api.paystack.co/transaction/initialize";

      $request = "";

      try
      {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_POST, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

          $headers = [
              'Authorization: Bearer ' . $this->sk,
              'Content-Type: application/json',
          ];
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

          $errNo = curl_errno($ch);
          $err = curl_error($ch);

          $request = curl_exec($ch);

          Log::info(" Request ==>  " . json_encode($request). json_encode($errNo). json_encode($err)  );

          curl_close($ch);

      }
      catch(\Exception $e)
      {

          Log::info(" Request Error ==>  " . json_encode($request) );
//          trigger_error(sprintf(
//              'Curl failed with error #%d: %s',
//              $e->getCode(), $e->getMessage()),
//              E_USER_ERROR);

      }


      if ($request) {
          $result = json_decode($request, true);
      } else {
          $result = false;
      }

      if(isset($result['status']) && $result['status']==true && isset($result['data']['authorization_url'])) {
        //initiation is correct
        $result['data']['phone']=$this->phone;
        $result['data']['amount']=$amount;
          $result['data']['registration_channel_id']=1;
        $ps=PayStack::create($result['data']);
      } else {
        $ps=null;
      }

      return $ps;
  }

  /**
  * validate payment fund/callback?trxref=9175257e52b85fdef9543702005ffad79a88565d&reference=9175257e52b85fdef9543702005ffad79a88565d
  *
  */
  public function validate($reference,$trxref)
  {
    $response=array('status'=>0,'message'=>'');

    $ps=PayStack::where('reference',$trxref)->first();
    if($ps==null)
    {
      $response['status']=404;
      $response['message']="Transaction not initiated";
    } else {
      //validate fully

      $this->authorize($ps->phone);
      $this->paystack=$ps; //set the paystack data into the class

      $result = array();
      //The parameter after verify/ is the transaction reference to be verified
      $url = 'https://api.paystack.co/transaction/verify/'.$reference;

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt(
        $ch, CURLOPT_HTTPHEADER, [
          'Authorization: Bearer ' . $this->sk]
      );
      $request = curl_exec($ch);
      curl_close($ch);

      if ($request) {
          $result = json_decode($request, true);
          // print_r($result);
          if($result){
            if(isset($result['data']) && $result['data']){
              //something came in
              if($result['data']['status'] == 'success'){
                // the transaction was successful, you can deliver value
                /*
                @ also remember that if this was a card transaction, you can store the
                @ card authorization to enable you charge the customer subsequently.
                @ The card authorization is in:
                @ $result['data']['authorization']['authorization_code'];
                @ PS: Store the authorization with this email address used for this transaction.
                @ The authorization will only work with this particular email.
                @ If the user changes his email on your system, it will be unusable
                */
                //echo "Transaction was successful";

                $response['status']=200;
                $response['message']="Transaction successful";

              }else{
                // the transaction was not successful, do not deliver value'
                // print_r($result);  //uncomment this line to inspect the result, to check why it failed.
                //echo "Transaction was not successful: Last gateway response was: ".$result['data']['gateway_response'];

                $response['status']=401;
                $response['message']=$result['data']['gateway_response'];
              }
            }else{
              //echo $result['message'];
              $response['status']=402;
              $response['message']=$result['message'];
            }
          }else{
            //print_r($result);
            $response['status']=403;
            $response['message']="Unknown error";
            //die("Something went wrong while trying to convert the request variable to json. Uncomment the print_r command to see what is in the result variable.");
          }
        }else{
          //var_dump($request);
          //die("Something went wrong while executing curl. Uncomment the var_dump line above this line to see what the issue is. Please check your CURL command to make sure everything is ok");
          $response['status']=403;
          $response['message']="Unknown error";
        }
    }

    return $response;
  }

  //credit the wallet truly
  public function credit()
  {
    //verify payment
    $this->paystack->status=1;

    $this->paystack->save();

    return $this->paystack;
  }


}
