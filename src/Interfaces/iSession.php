<?php
namespace Netsilik\Session\Interfaces;

/**
 * @package       Scepino/Session
 * @copyright (c) Scepino (http://scepino.com)
 * @license       EUPL-1.1 (European Union Public Licence, v1.1)
 */


interface iSession
{
	/**
	 * Get the current state of the session
	 *
	 * @return string The describing the state
	 */
	public function getState() : string;
	
	/**
	 * Create a new session
	 *
	 * @param int|null $userId The userId to bind this session to
	 *
	 * @return $this
	 */
	public function create(?int $userId) : iSession;
	
	/**
	 * Check existence of a session
	 * Note: a valid session with session='recoverable' still requires re-authenticating the user(!)
	 *
	 * @return bool True is we have a session (state='ok'|'recoverable') or false otherwise
	 */
	public function continue() : bool;
	
	/**
	 * Force a new sessionId to be regenerated and invalidate the old sessionId
	 *
	 * @return bool true on success, false if no session is active
	 */
	public function forceNewSessionId() : bool;
	
	/**
	 * Recover a session that needed rechecking the authentication of the user
	 *
	 * @return bool true on success, false if the session was not in a recoverable state
	 */
	public function recover() : bool;
	
	/**
	 * Destroy a session and unset all associated variables
	 *
	 * @return $this
	 */
	public function destroy() : iSession;
	
	/**
	 * Get the user currently bound to this session
	 *
	 * @return array Associative array with the userId and loginToken keys
	 */
	public function getUserInfo() : array;
	
	/**
	 * @return int|null false if the user is not successfully authenticated, an instance of a user object implementing the iSessionUser if this
	 *                  session is bound to a user
	 */
	public function getUserId() : ?int;
	
	/**
	 * @param int $userId
	 *
	 * @return $this
	 */
	public function setUserId(?int $userId) : iSession;
	
	/**
	 * Store some variable/value in the session so that we can use it in some subsequent request
	 *
	 * @param string $key   the name used to store this value
	 * @param mixed  $value the variable/value to store
	 *
	 * @return $this
	 */
	public function setData(string $key, $value) : iSession;
	
	/**
	 * Retrieve some variable/value stored in the session
	 *
	 * @param string $key the name used to store the value
	 *
	 * @return mixed null if the value could not be found, the originally stored variable otherwise
	 */
	public function getData(string $key);
	
	/**
	 * Remove some registered variable
	 *
	 * @param string $key the name used to originally store the variable
	 *
	 * @return $this
	 */
	public function deleteData(string $key) : iSession;
	
	/**
	 * @param string $key   the name used to store the value
	 * @param mixed  $value Set a flash message
	 *
	 * @return $this
	 */
	public function setFlashData(string $key, $value) : iSession;
	
	/**
	 * Get the flash message
	 *
	 * @param string $key the name used to store the value
	 *
	 * @return mixed
	 */
	public function getFlashData(string $key);
}
