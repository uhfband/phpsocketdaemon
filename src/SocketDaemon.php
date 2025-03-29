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

class SocketDaemon {
	private static $servers = array();
	private static $clients = array();

	public static function create_server($server_class, $client_class, $bind_address = 0, $bind_port = 0)
	{
		$server = new $server_class($client_class, $bind_address, $bind_port);
		if (!($server instanceof SocketServer)) {
			throw new SocketException("Invalid server class specified! Has to be a subclass of socketServer");
		}
		self::$servers[(int)$server->socket] = $server;
		
		return $server;
	}

	public static function create_client($client_class, $remote_address, $remote_port, $timeout = 0, $bind_address = 0, $bind_port = 0)
	{
		$client = new $client_class($bind_address, $bind_port);
		if (!($client instanceof SocketClient)) {
			throw new SocketException("Invalid client class specified! Has to be a subclass of SocketClient");
		}
		
		//$client->set_send_timeout(5, 0); //TODO
		//$client->set_recieve_timeout(5, 0); //TODO
		//var_dump($client->socket);
		//socket_set_timeout($client->socket, 5, 0); // 1ms
		$client->connect($remote_address, $remote_port, $timeout);
		self::$clients[(int)$client->socket] = $client;
		
		return $client;
	}

	private static function create_read_set()
	{
		$ret = array();
		foreach (self::$clients as $socket) {
			if ($socket->state !== Socket::STATE_CLOSED && $socket->state !== Socket::STATE_CONNECTING) {
				$ret[] = $socket->socket;
			}
			//$ret[] = $socket->socket;
		}
		foreach (self::$servers as $socket) {
			$ret[] = $socket->socket;
		}
		
		return $ret;
	}

	private static function create_write_set()
	{
		$ret = array();
		foreach (self::$clients as $socket) {
			if (!empty($socket->write_buffer) || ($socket->state === Socket::STATE_CONNECTING)) {
				$ret[] = $socket->socket;
			}
		}
		foreach (self::$servers as $socket) {
			if (!empty($socket->write_buffer)) {
				$ret[] = $socket->socket;
			}
		}
		return $ret;
	}

	private static function create_exception_set()
	{
		$ret = array();
		foreach (self::$clients as $socket) {
			if ($socket->state !== Socket::STATE_CLOSED) {
				$ret[] = $socket->socket;
			}
		}
		foreach (self::$servers as $socket) {
			if ($socket->state !== Socket::STATE_CLOSED) {
				$ret[] = $socket->socket;
			}
		}
		return $ret;
	}

	private static function clean_sockets()
	{
		foreach (self::$clients as $socket) {
			if ($socket->state === Socket::STATE_CONNECTING && $socket->connect_timeout > 0 && time() > $socket->connect_timeout) {
				$socket->close();
				$socket->on_connect_error('Connection timed out');
			}
			if ($socket->state === Socket::STATE_CLOSED) {
				unset(self::$clients[(int)$socket->socket]);
			}
		}
		foreach (self::$servers as $socket) {
			if ($socket->state === Socket::STATE_CLOSED) {
				unset(self::$servers[(int)$socket->socket]);
			}
		}
	}

	private static function get_class($socket)
	{
		if (isset(self::$clients[(int)$socket])) {
			return self::$clients[(int)$socket];
		} elseif (isset(self::$servers[(int)$socket])) {
			return self::$servers[(int)$socket];
		} else {
			throw new SocketException("Could not locate socket class for $socket");
		}
	}

	public static function process()
	{
		// if socketClient is in write set, and $socket->connecting === true, set connecting to false and call on_connect
		$read_set      = self::create_read_set();
		$write_set     = self::create_write_set();
		$exception_set = self::create_exception_set();
		$event_time    = time();

		//printf("before: r=%d w=%d e=%d \n", count($read_set), count($write_set), count($exception_set));
		//print_r($read_set);
		//print_r($write_set);
		//print_r($exception_set);

		while (($events = socket_select($read_set, $write_set, $exception_set, 1)) !== false) {
			//echo "Events: $events\n";
			//print_r($read_set);
			//print_r($write_set);
			//print_r($exception_set);
			//printf("events=%d r=%d w=%d e=%d \n", $events, count($read_set), count($write_set), count($exception_set));

			if ($events > 0) {
				
				foreach ($read_set as $socket) {
					$socket = self::get_class($socket);
					if ($socket instanceof SocketServer) {
						$client = $socket->accept();
						self::$clients[(int)$client->socket] = $client;
					} elseif($socket instanceof SocketClient) {
						// regular on_read event
						$socket->read();
					}
				}
				
				foreach ($write_set as $socket) {
					$socket = self::get_class($socket);
					if ($socket instanceof SocketClient) {
						if ($socket->state === Socket::STATE_CONNECTING) {
							if ($code = socket_get_option($socket->socket, SOL_SOCKET, SO_ERROR)) {
								$socket->close();
								$socket->on_connect_error(sprintf("[%d] %s", $code, socket_strerror($code)));
								//continue;
							} else {
								$socket->state = Socket::STATE_CONNECTED;
								$socket->on_connect();	
							}
							continue;
						}
						$socket->do_write();
					}
				}
				
				foreach ($exception_set as $socket) {
					$socket = self::get_class($socket);
					if ($socket instanceof SocketClient) {
						if ($socket->connecting) {
							$socket->on_connect_error();
						} else {
							$socket->on_disconnect();
						}
						
						if (isset(self::$clients[(int)$socket->socket])) {
							unset(self::$clients[(int)$socket->socket]);
						}
					}
				}
			}

			if (time() - $event_time >= 1) {
				// only do this if more then a second passed, else we'd keep looping this for every bit recieved
				foreach (self::$clients as $socket) {
					$socket->on_timer();
				}
				//TODO servers
				$event_time = time();
			}
			
			self::clean_sockets();
			$read_set      = self::create_read_set();
			$write_set     = self::create_write_set();
			$exception_set = self::create_exception_set();

			if (empty($read_set) && empty($write_set) && empty($exception_set)) {
				// no more sockets left, bail out
				echo "No more sockets left, exiting\n";
				break;
			}
		}
	}
}
