<?php

/**
 * @copyright (c) Scepino (http://scepino.com)
 * @license       EUPL-1.1 (European Union Public Licence, v1.1)
 */

namespace Netsilik\Session
{
	use Netsilik\Testing\Helpers\FunctionOverwrites;
	
	function session_regenerate_id(bool $delete_old_session = false) : bool
	{
		FunctionOverwrites::incrementCallCount(__FUNCTION__);
		
		if (FunctionOverwrites::isActive(__FUNCTION__)) {
			return FunctionOverwrites::shiftNextReturnValue(__FUNCTION__);
		}
		
		return \session_regenerate_id($delete_old_session);
	}
	
	function session_start(array $options = []) : bool
	{
		FunctionOverwrites::incrementCallCount(__FUNCTION__);
		
		if (FunctionOverwrites::isActive(__FUNCTION__)) {
			return FunctionOverwrites::shiftNextReturnValue(__FUNCTION__);
		}
		
		return \session_start($options);
	}
	
	function session_destroy() : bool
	{
		FunctionOverwrites::incrementCallCount(__FUNCTION__);
		
		if (FunctionOverwrites::isActive(__FUNCTION__)) {
			return FunctionOverwrites::shiftNextReturnValue(__FUNCTION__);
		}
		
		return \session_destroy();
	}
	
	function session_unset() : bool
	{
		FunctionOverwrites::incrementCallCount(__FUNCTION__);
		
		if (FunctionOverwrites::isActive(__FUNCTION__)) {
			return FunctionOverwrites::shiftNextReturnValue(__FUNCTION__);
		}
		
		return \session_unset(); // Since PHP 7.2 this function returns a boolean. PHP Storm is just not aware yet.
	}
}
