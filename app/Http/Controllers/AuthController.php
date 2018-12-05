<?php

namespace App\Http\Controllers;
use App\User;
use Validator;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Firebase\JWT\ExpiredException;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Laravel\Lumen\Routing\Controller as BaseController;

class AuthController extends BaseController 
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    private $request;
    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request) {
        $this->request = $request;
    }
    /**
     * Create a new token.
     * 
     * @param  \App\User   $user
     * @return string
     */
    protected function jwt(User $user) {
        $payload = [
            'iss' => "lumen-jwt", // Issuer of the token
            'sub' => $user->id, // Subject of the token
            'iat' => time(), // Time when JWT was issued. 
            'exp' => time() + 60*60 // Expiration time
        ];
        
        // As you can see we are passing `JWT_SECRET` as the second parameter that will 
        // be used to decode the token in the future.
        return JWT::encode($payload, env('JWT_SECRET'));
    } 
    /**
     * Authenticate a user and return the token if the provided credentials are correct.
     * 
     * @param  \App\User   $user 
     * @return mixed
     */
    public function login(User $user) {
        $this->validate($this->request, [
            'email'     => 'required|email',
            'password'  => 'required'
        ]);
        // Find the user by email
        $user = User::where('email', $this->request->input('email'))->first();
        if (!$user) {
            // You wil probably have some sort of helpers or whatever
            // to make sure that you have the same response format for
            // differents kind of responses. But let's return the 
            // below respose for now.
            return response()->json([
                'error' => 'Email does not exist.'
            ], 400);
        }
        // Verify the password and generate the token
        if (Hash::check($this->request->input('password'), $user->password)) {
            return response()->json([
                'token' => $this->jwt($user)
            ], 200);
        }
        // Bad Request response
        return response()->json([
            'error' => 'Email or password is wrong.'
        ], 400);
    }

    public function register(){
        $this->validate($this->request,[
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required',
        ]);

        $user = new User;

        $user->name = $this->request->name;
        $user->email = $this->request->email;
        $user->password = password_hash($this->request->password,PASSWORD_BCRYPT);

        if($user->save()){
            $client = new Client([
                'timeout' => 10,
                'base_uri' => route('/')
            ]);

            $requestBody = [
                'email' => $this->request->email,
                'password' => $this->request->password
            ];
            try{
                $request = $client->request('POST','/auth/login',[
                    'body' => json_encode($requestBody),
                    'defaults' => [
                        'exceptions' => false
                    ]
                ]);
                    
                $response = $request->getBody();
                $responseCode = $request->getStatusCode();
                $response = json_decode($response);

            }catch(ConnectException $e){
                $error = $e->getMessage();
                $errorCode = $e->getCode();

            }catch(ClientException $e){
                $error = $e->getMessage();
                $errorCode = $e->getCode();

            }catch(RequestException $e){
                $error = $e->getMessage();
                $errorCode = $e->getCode();
            }

            if(!isset($error)){
                return repsonse()->json([
                    'message' => 'oauth Token generated successfully',
                    'data' => $response,
                    'user' => $user,
                ],$responseCode);

            }else{
                DB::table('users')->where('email',$this->request->email)->delete();
                return response()->json([
                    'message' => $error
                ], $errorCode);
            }




        }
    }
}