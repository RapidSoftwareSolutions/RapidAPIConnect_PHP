#RapidAPI Connect - PHP SDK

This SDK allows you to connect to RapidAPI blocks from your php app. To start off, follow the following guide:

[![MIT Licence](https://badges.frapsoft.com/os/mit/mit.png?v=103)](https://opensource.org/licenses/mit-license.php)
[![forthebadge](http://forthebadge.com/images/badges/built-by-developers.svg)](http://forthebadge.com)

##Set-up:

First of all, download the sdk by composer:

    composer require rapidapi/rapidapi-connect

Then, require composer autoload and use package in your code:

    require __DIR__ . '/vendor/autoload.php';
    
    use RapidApi\RapidApiConnect;

Once required, the last step is to initialize the SDK with your project name and project API Key:

    $rapid = new RapidApiConnect('PROJECT_NAME', 'API_KEY');

That's all, your SDK is set up! You can now use any block by copying the code snippet from the marketplace.

##Usage:

To use any block in the marketplace, just copy it's code snippet and paste it in your code. For example, the following will call the **Calculate.add** block, and print the result:

    print_r($rapid->call('Calculate', 'add', ['num1' => 11, 'num2' => 2]));

The printed result will be:

    Array
    (
        [success] => 13
    )

**Notice** that the `error` event will also be called if you make an invalid block call (for example - the package you refer to does not exist).

##Files:
Whenever a block in RapidAPI requires a file, you can either pass a URL to the file or a read stream.

###URL:
The following code will call the block MicrosoftComputerVision.analyzeImage with a URL of an image:

```
    $response = $rapid->call('MicrosoftComputerVision', 'analyzeImage', 
    ['subscriptionKey' => '############################', 'image' => 'https://i.ytimg.com/vi/tntOCGkgt98/maxresdefault.jpg']
    );

```

###Read Stream
If the file is locally stored, you can read it using `CURLFile` and pass the read stream to the block, like the following:
```
    $image = new CURLFile('/YOUR_PATH_HERE/maxresdefault.jpg');
    
    $response = $rapid->call('MicrosoftComputerVision', 'analyzeImage', 
    ['subscriptionKey' => '############################', 'image' => $image]
    );
```

The printed result of `print_r($response)` will be:

    Array
    (
        [success] => {"categories":[{"name":"animal_cat","score":0.99609375}],"requestId":"6f7a1129-d6ff-4975-8725-d3593fc526c7","metadata":{"width":1600,"height":1200,"format":"Jpeg"}}
    )
    
RapidAPI uses the [form-data](https://github.com/form-data/form-data) library by [@felixge](https://github.com/felixge) to handle files, so please refer to it for more information.

###Webhooks
After setting up the webhook, you can listen to real-time events via websockets. 

```
require __DIR__ . "/vendor/autoload.php";

use Ratchet\Client\WebSocket;
use React\EventLoop\Factory;


$loop = Factory::create();

$rapid = new RapidApi\RapidApiConnect("PROJECT_NAME", "API_KEY");

$webhook = $rapid->connectionFactory($loop);

$webhook($rapid->getWebHookToken("Slack", "slashCommand"))
    ->then(function (WebSocket $websocket) use ($loop, $rapid) {

        return $rapid->createListener($websocket, $loop, ["token" => "your_token_here", "command" => "/slash_command"]);
    }, function (\Exception $e) use ($loop, $rapid) {

        echo $rapid->createCallback("close", $e->getMessage()) . PHP_EOL;
    })
    ->then(null, null, function ($notify) {

        echo $notify;
    });

$loop->run();
```

##Issues:

As this is a pre-release version of the SDK, you may expirience bugs. Please report them in the issues section to let us know. You may use the intercom chat on rapidapi.com for support at any time.

##Licence:

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

