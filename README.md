# Clockwork SMS API Wrapper for PHP

This wrapper lets you interact with Clockwork without the hassle of having to create any XML or make HTTP calls.

## What's Clockwork?

The mediaburst SMS API is being re-branded as Clockwork. At the same time we'll be launching some exciting new features
and these shiny new wrappers.

The terms Clockwork and "mediaburst SMS API" refer to exactly the same thing.

### Prerequisites

* A [Clockwork][2] account

## Usage

Require the Clockwork library:

	require 'class-Clockwork.php';

### Sending a message

    $clockwork = new Clockwork( $API_KEY );
    $message = array( 'to' => '07525071237', 'message' => 'This is a test!' );
    $result = $clockwork->send( $message );

### Sending multiple messages

We recommend you use batch sizes of 500 messages or fewer. By limiting the batch size it prevents any timeouts when sending.



### Handling the resposne

The responses come back as arrays, these contain the unique Clockwork message ID, whether the message worked, and the original SMS so you can update your database.



If you send multiple SMS messages in a single send, you'll get back an array of results, one per SMS.

The result will look something like this:



If a message fails, the reason for failure will be set in `error_code` and `error_message`.  

For example, if you send to invalid phone number "abc":



### Checking your credit

Check how many SMS credits you currently have available.


    
### Handling Errors

The Clockwork wrapper will throw exceptions if the entire call failed.


### Advanced Usage

This class has a few additional features that some users may find useful, if these are not set your account defaults will be used.

### Optional Parameters

*   From [string]

    The from address displayed on a phone when they receive a message

*   Long [nullable boolean]  

    Enable long SMS. A standard text can contain 160 characters, a long SMS supports up to 459.

*   Truncate [nullable boolean]  

    Truncate the message payload if it is too long, if this is set to false, the message will fail if it is too long.

*	InvalidCharacterAction [enum]

	What to do if the message contains an invalid character. Possible values are
	* AccountDefault - Use the setting from your Clockwork account
	* None			 - Fail the message
	* Remove		 - Remove the invalid characters then send
	* Replace		 - Replace some common invalid characters such as replacing curved quotes with straight quotes

### Setting Options

#### Global Options

Options set on the API object will apply to all SMS messages unless specifically overridden.

In this example both messages will be sent from Clockwork



#### Per-message Options

Set option values individually on each message

In this example, one message will be from Clockwork and the other from 84433

	Clockwork.API api = new API(key);
	List<SMS> smsList = new List<SMS>();
	smsList.Add(new SMS { To = "441234567891", Message = "Hello Bill", From="Clockwork" });
	smsList.Add(new SMS { To = "441234567892", Message = "Hello Ben", From="84433" });
	List<SMSResult> results = api.Send(smsList);

# License

This project is licensed under the ISC open-source license.

A copy of this license can be found in License.txt.

# Contributing

If you have any feedback on this wrapper drop us an email to hello@clockworksms.com.

The project is hosted on GitHub at https://github.com/mediaburst/clockwork-php.
If you would like to contribute a bug fix or improvement please fork the project 
and submit a pull request.

[1]: https://nuget.org/packages/Clockwork/
[2]: http://www.clockworksms.com/