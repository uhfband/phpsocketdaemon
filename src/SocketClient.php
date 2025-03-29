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

abstract class SocketClient extends Socket {
	public $remote_address = null;
	public $remote_port    = null;
	public $connect_timeout = 0;
	public $read_buffer    = '';
	public $write_buffer   = '';

	public function connect($remote_address, $remote_port, $timeout = 0)
	{
		$this->state = self::STATE_CONNECTING;
		if ($timeout > 0) {
			$this->connect_timeout = time() + $timeout;
		}
		try {
			parent::connect($remote_address, $remote_port);
		} catch (SocketException $e) {
			$this->close();
			$this->on_connect_error($e->getMessage());
		}
	}

	public function write($buffer, $length = 4096)
	{
		$this->write_buffer .= $buffer;
	}

	public function do_write()
	{
		$length = strlen($this->write_buffer);
		try {
			$written = parent::write($this->write_buffer, $length);
			if ($written < $length) {
				$this->write_buffer = substr($this->write_buffer, $written);
			} else {
				$this->write_buffer = '';
			}
			$this->on_write();
			return true;
		} catch (SocketException $e) {
			$this->close();
			$this->on_disconnect();
			return false;
		}
		return false;
	}

	public function read($length = 4096)
	{
		try {
			$this->read_buffer .= parent::read($length);
			$this->on_read();
		} catch (SocketException $e) {
			$this->close();
			$this->on_disconnect();
		}
	}

	public function on_connect() {}
	public function on_connect_error($message) {}
	public function on_disconnect() {}
	public function on_read() {}
	public function on_write() {}
	public function on_timer() {}
}
