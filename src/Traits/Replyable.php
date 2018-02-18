<?php

namespace Dacastro4\LaravelGmail\Traits;

use Dacastro4\LaravelGmail\Services\Message\Mail;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Support\Facades\Storage;
use Swift_Attachment;
use Swift_Message;

/**
 * @property Google_Service_Gmail $service
 */
trait Replyable
{

	private $swiftMessage;

	/**
	 * Gmail optional parameters
	 *
	 * @var array
	 */
	private $parameters = [];

	/**
	 * Text or html message to send
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Subject of the email
	 *
	 * @var string
	 */
	private $subject;

	/**
	 * Sender's email
	 *
	 * @var string
	 */
	private $from;

	/**
	 * Sender's name
	 *
	 * @var  string
	 */
	private $nameFrom;

	/**
	 * Email of the recipient
	 *
	 * @var string|array
	 */
	private $to;

	/**
	 * Name of the recipient
	 *
	 * @var string
	 */
	private $nameTo;

	/**
	 * Single email or array of email for a carbon copy
	 *
	 * @var array|string
	 */
	private $cc;

	/**
	 * Name of the recipient
	 *
	 * @var string
	 */
	private $nameCc;

	/**
	 * Single email or array of email for a blind carbon copy
	 *
	 * @var array|string
	 */
	private $bcc;

	/**
	 * Name of the recipient
	 *
	 * @var string
	 */
	private $nameBcc;

	/**
	 * List of attachments
	 *
	 * @var array
	 */
	private $attachments = [];

	private $priority = 2;

	public function __construct()
	{
		$this->swiftMessage = new Swift_Message();
	}

	/**
	 * Receives the recipient's
	 * If multiple recipients will receive the message an array should be used.
	 * Example: array('receiver@domain.org', 'other@domain.org' => 'A name')
	 *
	 * If $name is passed and the first parameter is a string, this name will be
	 * associated with the address.
	 *
	 * @param string|array $to
	 *
	 * @param string|null $name
	 *
	 * @return $this
	 */
	public function to( $to, $name = null )
	{
		$this->to = $to;
		$this->nameTo = $name;

		return $this;
	}

	public function from( $from, $name = null )
	{
		$this->from = $from;
		$this->nameTo = $name;

		return $this;
	}

	/**
	 * @param array|string $cc
	 *
	 * @param string|null $name
	 *
	 * @return $this
	 */
	public function cc( $cc, $name = null )
	{
		$this->cc = $this->emailList( $cc, $name );
		$this->nameCc = $name;

		return $this;
	}

	/**
	 * @param array|string $bcc
	 *
	 * @param string|null $name
	 *
	 * @return $this
	 */
	public function bcc( $bcc, $name = null )
	{
		$this->bcc = $this->emailList( $bcc, $name );
		$this->nameBcc = $name;

		return $this;
	}

	/**
	 * @param string $subject
	 *
	 * @return $this
	 */
	public function subject( $subject )
	{
		$this->subject = $subject;

		return $this;
	}

	/**
	 * @param string $message
	 *
	 * @return $this
	 */
	public function message( $message )
	{
		$this->message = $message;

		return $this;
	}

	/**
	 * Attaches new file to the email from the Storage folder
	 *
	 * @param $path
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function attach( $path )
	{

		$file = [];

		//TODO: Solve the attach issue. Which files should I attach? UploadFile it's an option.
		if ( Storage::has( $path ) ) {
			$content = Storage::get( $path );
			$name = Storage::name( $path );
			$file[ $name ] = $content;
		} else {
			throw new \Exception( 'File does not exists.' );
		}

		array_push( $this->attachments, $file );

		return $this;
	}

	/**
	 * The value is an integer where 1 is the highest priority and 5 is the lowest.
	 *
	 * @param int $priority
	 *
	 * @return $this
	 */
	public function priority( $priority )
	{
		$this->priority = $priority;

		return $this;
	}

	/**
	 * @param array $parameters
	 *
	 * @return $this
	 */
	public function optionalParameters( array $parameters )
	{
		$this->parameters = $parameters;

		return $this;
	}

	/**
	 * Reply to a specific email
	 *
	 * @return Mail
	 * @throws \Exception
	 */
	public function reply()
	{
		if ( ! $this->getId() ) {
			throw new \Exception( 'This is a new email. Use send().' );
		}

		$this->setReplyThreat();
		$this->setReplySubject();
		$body = $this->getMessageBody();
		$body->setThreadId( $this->getThreatId() );

		return new Mail( $this->service->users_messages->send( 'me', $body, $this->parameters ) );
	}

	/**
	 * Sends a new email
	 *
	 * @return Mail
	 */
	public function send()
	{
		$body = $this->getMessageBody();

		return new Mail( $this->service->users_messages->send( 'me', $body, $this->parameters ) );
	}

	/**
	 * Add a header to the email
	 *
	 * @param string $header
	 * @param string $value
	 */
	public function setHeader( $header, $value )
	{
		$headers = $this->swiftMessage->getHeaders();

		$headers->addTextHeader( $header, $value );

	}

	/**
	 * @return Google_Service_Gmail_Message
	 */
	private function getMessageBody()
	{
		$body = new Google_Service_Gmail_Message();

		$this->swiftMessage
			->setSubject( $this->subject )
			->setFrom( $this->from, $this->nameFrom )
			->setTo( $this->to, $this->nameTo )
			->setCc( $this->cc, $this->nameCc )
			->setBcc( $this->bcc, $this->nameBcc )
			->setBody( $this->message, 'text/html' )
			->setPriority( $this->priority );

		foreach ( $this->attachments as $file ) {
			$this->swiftMessage
				->attach( Swift_Attachment::fromPath( $file->path )->setFilename( $file->name ) );
		}

		$body->setRaw( $this->base64_encode( $this->swiftMessage->toString() ) );

		return $body;
	}

	private function base64_encode( $data )
	{
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private function emailList( $list, $name = null )
	{
		if ( is_array( $list ) ) {
			return $this->convertEmailList( $list, $name );
		} else {
			return $list;
		}
	}

	private function convertEmailList( $emails, $name = null )
	{
		$newList = [];
		$c = 0;
		foreach ( $emails as $key => $email ) {
			$emailName = isset( $name[ $c ] ) ? $name[ $c ] : explode( '@', $email )[ 0 ];
			$newList[ $email ] = $emailName;
			$c = $c + 1;
		}

		return $newList;
	}

	private function setReplySubject()
	{
		if ( ! $this->subject ) {
			$this->subject = $this->getSubject();
		}
	}

	private function setReplyThreat()
	{
		$threatId = $this->getThreatId();
		if ( $threatId ) {
			$this->setHeader( 'In-Reply-To', $this->getHeader( 'In-Reply-To' ) );
			$this->setHeader( 'References', $this->getHeader( 'References' ) );
			$this->setHeader( 'Message-ID', $this->getHeader( 'Message-ID' ) );
		}
	}

	public abstract function getThreatId();
}