<?php
/*
phpSocketDaemon 1.0
Copyright (C) 2006 Chris Chabot <chabotc@xs4all.nl>
See http://www.chabotc.nl/ for more information

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License as published by the Free Software Foundation; either
version 2.1 of the License, or (at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public
License along with this library; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace Uhf\PhpSocketDaemon;

abstract class SocketServerClient extends SocketClient {
	public $socket;
	public $remote_address;
	public $remote_port;
	public $local_addr;
	public $local_port;

	public function __construct($socket)
	{
		$this->socket = $socket;
		if (!is_resource($this->socket)) {
			throw new SocketException("Invalid socket or resource");
		}
		if (!socket_getsockname($this->socket, $this->local_addr, $this->local_port)) {
			throw new SocketException("Could not retrieve local address & port: " . socket_strerror(socket_last_error($this->socket)));
		}
		if (!socket_getpeername($this->socket, $this->remote_address, $this->remote_port)) {
			throw new SocketException("Could not retrieve remote address & port: " . socket_strerror(socket_last_error($this->socket)));
		}
		$this->set_non_block();
		$this->on_connect();
	}
}
