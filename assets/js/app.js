/*! For license information please see app.js.LICENSE.txt */
( () => {
	'use strict';
	var t,
		e = {
			463: () => {
				const t = jQuery( function ( t ) {} );
				function e( t ) {
					return (
						( e =
							'function' == typeof Symbol &&
							'symbol' == typeof Symbol.iterator
								? function ( t ) {
										return typeof t;
								  }
								: function ( t ) {
										return t &&
											'function' == typeof Symbol &&
											t.constructor === Symbol &&
											t !== Symbol.prototype
											? 'symbol'
											: typeof t;
								  } ),
						e( t )
					);
				}
				function r( t, e ) {
					var r =
						( 'undefined' != typeof Symbol &&
							t[ Symbol.iterator ] ) ||
						t[ '@@iterator' ];
					if ( ! r ) {
						if (
							Array.isArray( t ) ||
							( r = ( function ( t, e ) {
								if ( t ) {
									if ( 'string' == typeof t )
										return n( t, e );
									var r = {}.toString
										.call( t )
										.slice( 8, -1 );
									return (
										'Object' === r &&
											t.constructor &&
											( r = t.constructor.name ),
										'Map' === r || 'Set' === r
											? Array.from( t )
											: 'Arguments' === r ||
											  /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
													r
											  )
											? n( t, e )
											: void 0
									);
								}
							} )( t ) ) ||
							( e && t && 'number' == typeof t.length )
						) {
							r && ( t = r );
							var o = 0,
								a = function () {};
							return {
								s: a,
								n: function () {
									return o >= t.length
										? { done: ! 0 }
										: { done: ! 1, value: t[ o++ ] };
								},
								e: function ( t ) {
									throw t;
								},
								f: a,
							};
						}
						throw new TypeError(
							'Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					}
					var i,
						c = ! 0,
						s = ! 1;
					return {
						s: function () {
							r = r.call( t );
						},
						n: function () {
							var t = r.next();
							return ( c = t.done ), t;
						},
						e: function ( t ) {
							( s = ! 0 ), ( i = t );
						},
						f: function () {
							try {
								c || null == r.return || r.return();
							} finally {
								if ( s ) throw i;
							}
						},
					};
				}
				function n( t, e ) {
					( null == e || e > t.length ) && ( e = t.length );
					for ( var r = 0, n = Array( e ); r < e; r++ )
						n[ r ] = t[ r ];
					return n;
				}
				function o( t, e ) {
					var r = Object.keys( t );
					if ( Object.getOwnPropertySymbols ) {
						var n = Object.getOwnPropertySymbols( t );
						e &&
							( n = n.filter( function ( e ) {
								return Object.getOwnPropertyDescriptor( t, e )
									.enumerable;
							} ) ),
							r.push.apply( r, n );
					}
					return r;
				}
				function a( t, r, n ) {
					return (
						( r = ( function ( t ) {
							var r = ( function ( t, r ) {
								if ( 'object' != e( t ) || ! t ) return t;
								var n = t[ Symbol.toPrimitive ];
								if ( void 0 !== n ) {
									var o = n.call( t, r || 'default' );
									if ( 'object' != e( o ) ) return o;
									throw new TypeError(
										'@@toPrimitive must return a primitive value.'
									);
								}
								return ( 'string' === r ? String : Number )(
									t
								);
							} )( t, 'string' );
							return 'symbol' == e( r ) ? r : r + '';
						} )( r ) ) in t
							? Object.defineProperty( t, r, {
									value: n,
									enumerable: ! 0,
									configurable: ! 0,
									writable: ! 0,
							  } )
							: ( t[ r ] = n ),
						t
					);
				}
				const i = {
					ajax: function ( t ) {
						var e =
								arguments.length > 1 &&
								void 0 !== arguments[ 1 ]
									? arguments[ 1 ]
									: {},
							r =
								arguments.length > 2 &&
								void 0 !== arguments[ 2 ]
									? arguments[ 2 ]
									: 'POST';
						return new Promise( function ( n, i ) {
							var c,
								s,
								u = ( function ( t ) {
									for (
										var e = 1;
										e < arguments.length;
										e++
									) {
										var r =
											null != arguments[ e ]
												? arguments[ e ]
												: {};
										e % 2
											? o( Object( r ), ! 0 ).forEach(
													function ( e ) {
														a( t, e, r[ e ] );
													}
											  )
											: Object.getOwnPropertyDescriptors
											? Object.defineProperties(
													t,
													Object.getOwnPropertyDescriptors(
														r
													)
											  )
											: o( Object( r ) ).forEach(
													function ( e ) {
														Object.defineProperty(
															t,
															e,
															Object.getOwnPropertyDescriptor(
																r,
																e
															)
														);
													}
											  );
									}
									return t;
								} )(
									{
										action: t,
										nonce:
											( null === ( c = window.aieData ) ||
											void 0 === c
												? void 0
												: c.nonce ) || '',
									},
									e
								);
							jQuery
								.ajax( {
									url:
										( null === ( s = window.aieData ) ||
										void 0 === s
											? void 0
											: s.ajaxUrl ) ||
										'/wp-admin/admin-ajax.php',
									type: r,
									data: u,
									dataType: 'json',
								} )
								.done( function ( t ) {
									var e;
									t.success
										? n( t.data || t )
										: i(
												( null === ( e = t.data ) ||
												void 0 === e
													? void 0
													: e.message ) ||
													'Request failed'
										  );
								} )
								.fail( function ( t, e, r ) {
									i(
										'AJAX Error: '
											.concat( e, ' - ' )
											.concat( r )
									);
								} );
						} );
					},
					formatFileSize: function ( t ) {
						if ( 0 === t ) return '0 Bytes';
						var e = Math.floor( Math.log( t ) / Math.log( 1024 ) );
						return (
							Math.round( ( t / Math.pow( 1024, e ) ) * 100 ) /
								100 +
							' ' +
							[ 'Bytes', 'KB', 'MB', 'GB' ][ e ]
						);
					},
					formatDuration: function ( t ) {
						if ( t < 60 ) return Math.round( t ) + 's';
						var e = Math.floor( t / 60 ),
							r = Math.round( t % 60 );
						if ( e < 60 )
							return ''.concat( e, 'm ' ).concat( r, 's' );
						var n = Math.floor( e / 60 ),
							o = e % 60;
						return ''.concat( n, 'h ' ).concat( o, 'm' );
					},
					debounce: function ( t ) {
						var e,
							r =
								arguments.length > 1 &&
								void 0 !== arguments[ 1 ]
									? arguments[ 1 ]
									: 300;
						return function () {
							for (
								var n = arguments.length,
									o = new Array( n ),
									a = 0;
								a < n;
								a++
							)
								o[ a ] = arguments[ a ];
							var i = this;
							clearTimeout( e ),
								( e = setTimeout( function () {
									return t.apply( i, o );
								}, r ) );
						};
					},
					showNotice: function ( t ) {
						var e = 'notice notice-'.concat(
								arguments.length > 1 &&
									void 0 !== arguments[ 1 ]
									? arguments[ 1 ]
									: 'info',
								' is-dismissible'
							),
							r = '\n\t\t\t<div class="'
								.concat( e, '">\n\t\t\t\t<p>' )
								.concat(
									t,
									'</p>\n\t\t\t\t<button type="button" class="notice-dismiss">\n\t\t\t\t\t<span class="screen-reader-text">Dismiss this notice.</span>\n\t\t\t\t</button>\n\t\t\t</div>\n\t\t'
								),
							n = jQuery( r );
						jQuery( '.wrap > h1' ).after( n ),
							setTimeout( function () {
								n.fadeOut( function () {
									return n.remove();
								} );
							}, 5e3 ),
							n.on( 'click', '.notice-dismiss', function () {
								n.fadeOut( function () {
									return n.remove();
								} );
							} );
					},
					validateFile: function ( t ) {
						var e =
								arguments.length > 1 &&
								void 0 !== arguments[ 1 ]
									? arguments[ 1 ]
									: [],
							r =
								arguments.length > 2 &&
								void 0 !== arguments[ 2 ]
									? arguments[ 2 ]
									: 52428800,
							n = [];
						if (
							( t.size > r &&
								n.push(
									'File size ('
										.concat(
											this.formatFileSize( t.size ),
											') exceeds maximum allowed size ('
										)
										.concat( this.formatFileSize( r ), ')' )
								),
							e.length > 0 )
						) {
							var o = t.name.split( '.' ).pop().toLowerCase();
							e.some( function ( e ) {
								return e.startsWith( '.' )
									? e.substring( 1 ) === o
									: t.type === e;
							} ) ||
								n.push(
									'File type .'
										.concat(
											o,
											' is not allowed. Allowed types: '
										)
										.concat( e.join( ', ' ) )
								);
						}
						return { valid: 0 === n.length, errors: n };
					},
					parseCSV: function ( t ) {
						var e,
							n =
								arguments.length > 1 &&
								void 0 !== arguments[ 1 ]
									? arguments[ 1 ]
									: ',',
							o = [],
							a = r( t.split( '\n' ) );
						try {
							for ( a.s(); ! ( e = a.n() ).done;  ) {
								var i = e.value;
								if ( '' !== i.trim() ) {
									for (
										var c = [], s = '', u = ! 1, l = 0;
										l < i.length;
										l++
									) {
										var p = i[ l ];
										'"' === p
											? ( u = ! u )
											: p !== n || u
											? ( s += p )
											: ( c.push( s.trim() ),
											  ( s = '' ) );
									}
									c.push( s.trim() ), o.push( c );
								}
							}
						} catch ( t ) {
							a.e( t );
						} finally {
							a.f();
						}
						return o;
					},
					escapeHtml: function ( t ) {
						var e = document.createElement( 'div' );
						return ( e.textContent = t ), e.innerHTML;
					},
					getUrlParameter: function ( t ) {
						return new URLSearchParams(
							window.location.search
						).get( t );
					},
					downloadFile: function ( t, e ) {
						var r = document.createElement( 'a' );
						( r.href = t ),
							( r.download = e || 'export.csv' ),
							document.body.appendChild( r ),
							r.click(),
							document.body.removeChild( r );
					},
					generateUUID: function () {
						return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(
							/[xy]/g,
							function ( t ) {
								var e = ( 16 * Math.random() ) | 0;
								return (
									'x' === t ? e : ( 3 & e ) | 8
								).toString( 16 );
							}
						);
					},
					createProgressBar: function () {
						return jQuery(
							'\n\t\t\t<div class="aie-progress-container">\n\t\t\t\t<div class="aie-progress-bar">\n\t\t\t\t\t<div class="aie-progress-bar-fill" style="width: 0%;"></div>\n\t\t\t\t</div>\n\t\t\t\t<div class="aie-progress-stats">\n\t\t\t\t\t<div class="aie-progress-percentage">0%</div>\n\t\t\t\t\t<div class="aie-progress-details">\n\t\t\t\t\t\t<span class="aie-processed">0</span> / <span class="aie-total">0</span> items\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t'
						);
					},
					updateProgressBar: function ( t, e ) {
						var r = e.percentage || 0,
							n = e.processed || 0,
							o = e.total || 0;
						t
							.find( '.aie-progress-bar-fill' )
							.css( 'width', r + '%' ),
							t
								.find( '.aie-progress-percentage' )
								.text( Math.round( r ) + '%' ),
							t.find( '.aie-processed' ).text( n ),
							t.find( '.aie-total' ).text( o ),
							e.estimates &&
								( e.estimates.elapsed_formatted &&
									t
										.find( '.aie-elapsed-time' )
										.text( e.estimates.elapsed_formatted ),
								e.estimates.remaining_formatted &&
									t
										.find( '.aie-remaining-time' )
										.text(
											e.estimates.remaining_formatted
										),
								e.estimates.items_per_second &&
									t
										.find( '.aie-items-per-second' )
										.text(
											e.estimates.items_per_second.toFixed(
												1
											) + ' items/s'
										) );
					},
					handleError: function ( t ) {
						var e =
							arguments.length > 1 && void 0 !== arguments[ 1 ]
								? arguments[ 1 ]
								: '';
						console.error(
							'AIE Error'.concat( e ? ' (' + e + ')' : '', ':' ),
							t
						);
						var r = t.message || t.toString();
						this.showNotice( r, 'error' );
					},
				};
				function c( t ) {
					return (
						( c =
							'function' == typeof Symbol &&
							'symbol' == typeof Symbol.iterator
								? function ( t ) {
										return typeof t;
								  }
								: function ( t ) {
										return t &&
											'function' == typeof Symbol &&
											t.constructor === Symbol &&
											t !== Symbol.prototype
											? 'symbol'
											: typeof t;
								  } ),
						c( t )
					);
				}
				function s() {
					s = function () {
						return e;
					};
					var t,
						e = {},
						r = Object.prototype,
						n = r.hasOwnProperty,
						o =
							Object.defineProperty ||
							function ( t, e, r ) {
								t[ e ] = r.value;
							},
						a = 'function' == typeof Symbol ? Symbol : {},
						i = a.iterator || '@@iterator',
						u = a.asyncIterator || '@@asyncIterator',
						l = a.toStringTag || '@@toStringTag';
					function p( t, e, r ) {
						return (
							Object.defineProperty( t, e, {
								value: r,
								enumerable: ! 0,
								configurable: ! 0,
								writable: ! 0,
							} ),
							t[ e ]
						);
					}
					try {
						p( {}, '' );
					} catch ( t ) {
						p = function ( t, e, r ) {
							return ( t[ e ] = r );
						};
					}
					function f( t, e, r, n ) {
						var a = e && e.prototype instanceof w ? e : w,
							i = Object.create( a.prototype ),
							c = new C( n || [] );
						return o( i, '_invoke', { value: F( t, r, c ) } ), i;
					}
					function d( t, e, r ) {
						try {
							return { type: 'normal', arg: t.call( e, r ) };
						} catch ( t ) {
							return { type: 'throw', arg: t };
						}
					}
					e.wrap = f;
					var h = 'suspendedStart',
						v = 'suspendedYield',
						y = 'executing',
						m = 'completed',
						g = {};
					function w() {}
					function x() {}
					function j() {}
					var b = {};
					p( b, i, function () {
						return this;
					} );
					var _ = Object.getPrototypeOf,
						Q = _ && _( _( P( [] ) ) );
					Q && Q !== r && n.call( Q, i ) && ( b = Q );
					var S = ( j.prototype = w.prototype = Object.create( b ) );
					function k( t ) {
						[ 'next', 'throw', 'return' ].forEach( function ( e ) {
							p( t, e, function ( t ) {
								return this._invoke( e, t );
							} );
						} );
					}
					function E( t, e ) {
						function r( o, a, i, s ) {
							var u = d( t[ o ], t, a );
							if ( 'throw' !== u.type ) {
								var l = u.arg,
									p = l.value;
								return p &&
									'object' == c( p ) &&
									n.call( p, '__await' )
									? e.resolve( p.__await ).then(
											function ( t ) {
												r( 'next', t, i, s );
											},
											function ( t ) {
												r( 'throw', t, i, s );
											}
									  )
									: e.resolve( p ).then(
											function ( t ) {
												( l.value = t ), i( l );
											},
											function ( t ) {
												return r( 'throw', t, i, s );
											}
									  );
							}
							s( u.arg );
						}
						var a;
						o( this, '_invoke', {
							value: function ( t, n ) {
								function o() {
									return new e( function ( e, o ) {
										r( t, n, e, o );
									} );
								}
								return ( a = a ? a.then( o, o ) : o() );
							},
						} );
					}
					function F( e, r, n ) {
						var o = h;
						return function ( a, i ) {
							if ( o === y )
								throw Error( 'Generator is already running' );
							if ( o === m ) {
								if ( 'throw' === a ) throw i;
								return { value: t, done: ! 0 };
							}
							for ( n.method = a, n.arg = i; ;  ) {
								var c = n.delegate;
								if ( c ) {
									var s = L( c, n );
									if ( s ) {
										if ( s === g ) continue;
										return s;
									}
								}
								if ( 'next' === n.method )
									n.sent = n._sent = n.arg;
								else if ( 'throw' === n.method ) {
									if ( o === h ) throw ( ( o = m ), n.arg );
									n.dispatchException( n.arg );
								} else
									'return' === n.method &&
										n.abrupt( 'return', n.arg );
								o = y;
								var u = d( e, r, n );
								if ( 'normal' === u.type ) {
									if (
										( ( o = n.done ? m : v ), u.arg === g )
									)
										continue;
									return { value: u.arg, done: n.done };
								}
								'throw' === u.type &&
									( ( o = m ),
									( n.method = 'throw' ),
									( n.arg = u.arg ) );
							}
						};
					}
					function L( e, r ) {
						var n = r.method,
							o = e.iterator[ n ];
						if ( o === t )
							return (
								( r.delegate = null ),
								( 'throw' === n &&
									e.iterator.return &&
									( ( r.method = 'return' ),
									( r.arg = t ),
									L( e, r ),
									'throw' === r.method ) ) ||
									( 'return' !== n &&
										( ( r.method = 'throw' ),
										( r.arg = new TypeError(
											"The iterator does not provide a '" +
												n +
												"' method"
										) ) ) ),
								g
							);
						var a = d( o, e.iterator, r.arg );
						if ( 'throw' === a.type )
							return (
								( r.method = 'throw' ),
								( r.arg = a.arg ),
								( r.delegate = null ),
								g
							);
						var i = a.arg;
						return i
							? i.done
								? ( ( r[ e.resultName ] = i.value ),
								  ( r.next = e.nextLoc ),
								  'return' !== r.method &&
										( ( r.method = 'next' ),
										( r.arg = t ) ),
								  ( r.delegate = null ),
								  g )
								: i
							: ( ( r.method = 'throw' ),
							  ( r.arg = new TypeError(
									'iterator result is not an object'
							  ) ),
							  ( r.delegate = null ),
							  g );
					}
					function O( t ) {
						var e = { tryLoc: t[ 0 ] };
						1 in t && ( e.catchLoc = t[ 1 ] ),
							2 in t &&
								( ( e.finallyLoc = t[ 2 ] ),
								( e.afterLoc = t[ 3 ] ) ),
							this.tryEntries.push( e );
					}
					function I( t ) {
						var e = t.completion || {};
						( e.type = 'normal' ),
							delete e.arg,
							( t.completion = e );
					}
					function C( t ) {
						( this.tryEntries = [ { tryLoc: 'root' } ] ),
							t.forEach( O, this ),
							this.reset( ! 0 );
					}
					function P( e ) {
						if ( e || '' === e ) {
							var r = e[ i ];
							if ( r ) return r.call( e );
							if ( 'function' == typeof e.next ) return e;
							if ( ! isNaN( e.length ) ) {
								var o = -1,
									a = function r() {
										for ( ; ++o < e.length;  )
											if ( n.call( e, o ) )
												return (
													( r.value = e[ o ] ),
													( r.done = ! 1 ),
													r
												);
										return (
											( r.value = t ), ( r.done = ! 0 ), r
										);
									};
								return ( a.next = a );
							}
						}
						throw new TypeError( c( e ) + ' is not iterable' );
					}
					return (
						( x.prototype = j ),
						o( S, 'constructor', { value: j, configurable: ! 0 } ),
						o( j, 'constructor', { value: x, configurable: ! 0 } ),
						( x.displayName = p( j, l, 'GeneratorFunction' ) ),
						( e.isGeneratorFunction = function ( t ) {
							var e = 'function' == typeof t && t.constructor;
							return (
								!! e &&
								( e === x ||
									'GeneratorFunction' ===
										( e.displayName || e.name ) )
							);
						} ),
						( e.mark = function ( t ) {
							return (
								Object.setPrototypeOf
									? Object.setPrototypeOf( t, j )
									: ( ( t.__proto__ = j ),
									  p( t, l, 'GeneratorFunction' ) ),
								( t.prototype = Object.create( S ) ),
								t
							);
						} ),
						( e.awrap = function ( t ) {
							return { __await: t };
						} ),
						k( E.prototype ),
						p( E.prototype, u, function () {
							return this;
						} ),
						( e.AsyncIterator = E ),
						( e.async = function ( t, r, n, o, a ) {
							void 0 === a && ( a = Promise );
							var i = new E( f( t, r, n, o ), a );
							return e.isGeneratorFunction( r )
								? i
								: i.next().then( function ( t ) {
										return t.done ? t.value : i.next();
								  } );
						} ),
						k( S ),
						p( S, l, 'Generator' ),
						p( S, i, function () {
							return this;
						} ),
						p( S, 'toString', function () {
							return '[object Generator]';
						} ),
						( e.keys = function ( t ) {
							var e = Object( t ),
								r = [];
							for ( var n in e ) r.push( n );
							return (
								r.reverse(),
								function t() {
									for ( ; r.length;  ) {
										var n = r.pop();
										if ( n in e )
											return (
												( t.value = n ),
												( t.done = ! 1 ),
												t
											);
									}
									return ( t.done = ! 0 ), t;
								}
							);
						} ),
						( e.values = P ),
						( C.prototype = {
							constructor: C,
							reset: function ( e ) {
								if (
									( ( this.prev = 0 ),
									( this.next = 0 ),
									( this.sent = this._sent = t ),
									( this.done = ! 1 ),
									( this.delegate = null ),
									( this.method = 'next' ),
									( this.arg = t ),
									this.tryEntries.forEach( I ),
									! e )
								)
									for ( var r in this )
										't' === r.charAt( 0 ) &&
											n.call( this, r ) &&
											! isNaN( +r.slice( 1 ) ) &&
											( this[ r ] = t );
							},
							stop: function () {
								this.done = ! 0;
								var t = this.tryEntries[ 0 ].completion;
								if ( 'throw' === t.type ) throw t.arg;
								return this.rval;
							},
							dispatchException: function ( e ) {
								if ( this.done ) throw e;
								var r = this;
								function o( n, o ) {
									return (
										( c.type = 'throw' ),
										( c.arg = e ),
										( r.next = n ),
										o &&
											( ( r.method = 'next' ),
											( r.arg = t ) ),
										!! o
									);
								}
								for (
									var a = this.tryEntries.length - 1;
									a >= 0;
									--a
								) {
									var i = this.tryEntries[ a ],
										c = i.completion;
									if ( 'root' === i.tryLoc )
										return o( 'end' );
									if ( i.tryLoc <= this.prev ) {
										var s = n.call( i, 'catchLoc' ),
											u = n.call( i, 'finallyLoc' );
										if ( s && u ) {
											if ( this.prev < i.catchLoc )
												return o( i.catchLoc, ! 0 );
											if ( this.prev < i.finallyLoc )
												return o( i.finallyLoc );
										} else if ( s ) {
											if ( this.prev < i.catchLoc )
												return o( i.catchLoc, ! 0 );
										} else {
											if ( ! u )
												throw Error(
													'try statement without catch or finally'
												);
											if ( this.prev < i.finallyLoc )
												return o( i.finallyLoc );
										}
									}
								}
							},
							abrupt: function ( t, e ) {
								for (
									var r = this.tryEntries.length - 1;
									r >= 0;
									--r
								) {
									var o = this.tryEntries[ r ];
									if (
										o.tryLoc <= this.prev &&
										n.call( o, 'finallyLoc' ) &&
										this.prev < o.finallyLoc
									) {
										var a = o;
										break;
									}
								}
								a &&
									( 'break' === t || 'continue' === t ) &&
									a.tryLoc <= e &&
									e <= a.finallyLoc &&
									( a = null );
								var i = a ? a.completion : {};
								return (
									( i.type = t ),
									( i.arg = e ),
									a
										? ( ( this.method = 'next' ),
										  ( this.next = a.finallyLoc ),
										  g )
										: this.complete( i )
								);
							},
							complete: function ( t, e ) {
								if ( 'throw' === t.type ) throw t.arg;
								return (
									'break' === t.type || 'continue' === t.type
										? ( this.next = t.arg )
										: 'return' === t.type
										? ( ( this.rval = this.arg = t.arg ),
										  ( this.method = 'return' ),
										  ( this.next = 'end' ) )
										: 'normal' === t.type &&
										  e &&
										  ( this.next = e ),
									g
								);
							},
							finish: function ( t ) {
								for (
									var e = this.tryEntries.length - 1;
									e >= 0;
									--e
								) {
									var r = this.tryEntries[ e ];
									if ( r.finallyLoc === t )
										return (
											this.complete(
												r.completion,
												r.afterLoc
											),
											I( r ),
											g
										);
								}
							},
							catch: function ( t ) {
								for (
									var e = this.tryEntries.length - 1;
									e >= 0;
									--e
								) {
									var r = this.tryEntries[ e ];
									if ( r.tryLoc === t ) {
										var n = r.completion;
										if ( 'throw' === n.type ) {
											var o = n.arg;
											I( r );
										}
										return o;
									}
								}
								throw Error( 'illegal catch attempt' );
							},
							delegateYield: function ( e, r, n ) {
								return (
									( this.delegate = {
										iterator: P( e ),
										resultName: r,
										nextLoc: n,
									} ),
									'next' === this.method && ( this.arg = t ),
									g
								);
							},
						} ),
						e
					);
				}
				function u( t, e, r, n, o, a, i ) {
					try {
						var c = t[ a ]( i ),
							s = c.value;
					} catch ( t ) {
						return void r( t );
					}
					c.done ? e( s ) : Promise.resolve( s ).then( n, o );
				}
				function l( t ) {
					return function () {
						var e = this,
							r = arguments;
						return new Promise( function ( n, o ) {
							var a = t.apply( e, r );
							function i( t ) {
								u( a, n, o, i, c, 'next', t );
							}
							function c( t ) {
								u( a, n, o, i, c, 'throw', t );
							}
							i( void 0 );
						} );
					};
				}
				const p = {
					currentStep: 1,
					totalSteps: 6,
					uploadedFile: null,
					fileData: null,
					jobId: null,
					progressInterval: null,
					init: function () {
						jQuery( '#wp-aie-import' ).length &&
							( this.bindEvents(), this.showStep( 1 ) );
					},
					bindEvents: function () {
						var t = this,
							e = jQuery( '#wp-aie-import' );
						e.on( 'click', '.aie-next-step', function () {
							return t.nextStep();
						} ),
							e.on( 'click', '.aie-prev-step', function () {
								return t.prevStep();
							} ),
							e.on(
								'change',
								'input[name="content_type"]',
								function ( e ) {
									return t.onContentTypeChange( e );
								}
							),
							jQuery( '#aie-select-file' ).on(
								'click',
								function () {
									return jQuery( '#aie-file-input' ).click();
								}
							),
							jQuery( '#aie-file-input' ).on(
								'change',
								function ( e ) {
									return t.onFileSelect( e );
								}
							),
							jQuery( '.aie-remove-file' ).on(
								'click',
								function () {
									return t.removeFile();
								}
							);
						var r = jQuery( '#aie-upload-area' );
						r
							.on( 'dragover', function ( t ) {
								t.preventDefault(),
									r.addClass( 'aie-dragover' );
							} )
							.on( 'dragleave', function () {
								r.removeClass( 'aie-dragover' );
							} )
							.on( 'drop', function ( e ) {
								e.preventDefault(),
									r.removeClass( 'aie-dragover' );
								var n = e.originalEvent.dataTransfer.files;
								n.length > 0 && t.handleFile( n[ 0 ] );
							} ),
							e.on( 'click', '.aie-auto-map', function () {
								return t.autoMapFields();
							} ),
							e.on( 'click', '.aie-clear-map', function () {
								return t.clearFieldMapping();
							} ),
							e.on( 'click', '.aie-start-import', function () {
								return t.startImport();
							} ),
							e.on( 'click', '.aie-cancel-import', function () {
								return t.cancelImport();
							} ),
							e.on( 'click', '.aie-new-import', function () {
								return t.resetWizard();
							} ),
							e.on( 'click', '.aie-toggle-logs', function () {
								return t.toggleLogs();
							} );
					},
					showStep: function ( t ) {
						var e = jQuery( '#wp-aie-import' );
						e.find( '.aie-step' ).removeClass( 'active' ),
							e
								.find( '.aie-step-'.concat( t ) )
								.addClass( 'active' ),
							e
								.find( '.aie-step-indicator' )
								.removeClass( 'active completed' ),
							e
								.find(
									'.aie-step-indicator[data-step="'.concat(
										t,
										'"]'
									)
								)
								.addClass( 'active' ),
							e
								.find( '.aie-step-indicator[data-step]' )
								.filter( function () {
									return jQuery( this ).data( 'step' ) < t;
								} )
								.addClass( 'completed' ),
							( this.currentStep = t ),
							3 === t
								? this.loadPreview()
								: 4 === t && this.buildFieldMapping();
					},
					nextStep: function () {
						this.currentStep < this.totalSteps &&
							this.validateStep( this.currentStep ) &&
							this.showStep( this.currentStep + 1 );
					},
					prevStep: function () {
						this.currentStep > 1 &&
							this.showStep( this.currentStep - 1 );
					},
					validateStep: function ( t ) {
						switch ( t ) {
							case 2:
								if ( ! this.uploadedFile )
									return (
										i.showNotice(
											'Please upload a file',
											'error'
										),
										! 1
									);
								break;
							case 4:
								var e = this.getFieldMapping();
								if ( 0 === Object.keys( e ).length )
									return (
										i.showNotice(
											'Please map at least one field',
											'error'
										),
										! 1
									);
						}
						return ! 0;
					},
					onContentTypeChange: function ( t ) {
						'media' === jQuery( t.target ).val()
							? ( jQuery( '.aie-post-options' ).hide(),
							  jQuery( '.aie-media-options' ).show() )
							: ( jQuery( '.aie-post-options' ).show(),
							  jQuery( '.aie-media-options' ).hide() );
					},
					onFileSelect: function ( t ) {
						var e = t.target.files[ 0 ];
						e && this.handleFile( e );
					},
					handleFile: function ( t ) {
						var e = i.validateFile(
							t,
							[ '.csv', '.json', '.xml' ],
							52428800
						);
						if ( e.valid ) {
							( this.uploadedFile = t ),
								jQuery( '.aie-upload-placeholder' ).hide(),
								jQuery( '.aie-file-info' ).show(),
								jQuery( '.aie-file-name' ).text( t.name ),
								jQuery( '.aie-file-size' ).text(
									i.formatFileSize( t.size )
								);
							var r = this.detectFormat( t.name );
							jQuery( '.aie-file-format' ).text(
								r.toUpperCase()
							),
								'csv' === r &&
									( jQuery( '.aie-format-options' ).show(),
									jQuery( '.aie-csv-options' ).show() ),
								jQuery( '.aie-step-2 .aie-next-step' ).prop(
									'disabled',
									! 1
								),
								this.uploadFile( t );
						} else i.showNotice( e.errors.join( '<br>' ), 'error' );
					},
					uploadFile: function ( t ) {
						var e = this;
						return l(
							s().mark( function r() {
								var n, o, a, c, u;
								return s().wrap(
									function ( r ) {
										for (;;)
											switch ( ( r.prev = r.next ) ) {
												case 0:
													return (
														( o =
															new FormData() ).append(
															'file',
															t
														),
														o.append(
															'action',
															'aie_import_upload_file'
														),
														o.append(
															'nonce',
															( null ===
																( n =
																	window.aieData ) ||
															void 0 === n
																? void 0
																: n.nonce ) ||
																''
														),
														o.append(
															'content_type',
															jQuery(
																'input[name="content_type"]:checked'
															).val()
														),
														( r.prev = 5 ),
														( r.next = 8 ),
														jQuery.ajax( {
															url:
																( null ===
																	( a =
																		window.aieData ) ||
																void 0 === a
																	? void 0
																	: a.ajaxUrl ) ||
																'/wp-admin/admin-ajax.php',
															type: 'POST',
															data: o,
															processData: ! 1,
															contentType: ! 1,
															dataType: 'json',
														} )
													);
												case 8:
													if (
														! ( c = r.sent ).success
													) {
														r.next = 14;
														break;
													}
													( e.fileData = c.data ),
														i.showNotice(
															'File uploaded successfully',
															'success'
														),
														( r.next = 15 );
													break;
												case 14:
													throw new Error(
														( null ===
															( u = c.data ) ||
														void 0 === u
															? void 0
															: u.message ) ||
															'Upload failed'
													);
												case 15:
													r.next = 21;
													break;
												case 17:
													( r.prev = 17 ),
														( r.t0 = r.catch( 5 ) ),
														i.handleError(
															r.t0,
															'File upload'
														),
														e.removeFile();
												case 21:
												case 'end':
													return r.stop();
											}
									},
									r,
									null,
									[ [ 5, 17 ] ]
								);
							} )
						)();
					},
					removeFile: function () {
						( this.uploadedFile = null ),
							( this.fileData = null ),
							jQuery( '.aie-file-info' ).hide(),
							jQuery( '.aie-upload-placeholder' ).show(),
							jQuery( '.aie-format-options' ).hide(),
							jQuery( '#aie-file-input' ).val( '' ),
							jQuery( '.aie-step-2 .aie-next-step' ).prop(
								'disabled',
								! 0
							);
					},
					detectFormat: function ( t ) {
						var e = t.split( '.' ).pop().toLowerCase();
						return [ 'csv', 'json', 'xml' ].includes( e )
							? e
							: 'csv';
					},
					loadPreview: function () {
						var t = this;
						return l(
							s().mark( function e() {
								var r, n, o, a, c;
								return s().wrap( function ( e ) {
									for (;;)
										switch ( ( e.prev = e.next ) ) {
											case 0:
												if (
													t.fileData &&
													t.fileData.preview
												) {
													e.next = 2;
													break;
												}
												return e.abrupt( 'return' );
											case 2:
												( n = t.fileData.preview ),
													( o =
														jQuery(
															'.aie-preview-table'
														) ),
													jQuery(
														'.aie-total-rows'
													).text(
														t.fileData.total_rows ||
															0
													),
													jQuery(
														'.aie-total-columns'
													).text(
														( null ===
															( r =
																t.fileData
																	.columns ) ||
														void 0 === r
															? void 0
															: r.length ) || 0
													),
													( a = '<tr>' ),
													n.headers &&
														n.headers.forEach(
															function ( t ) {
																a +=
																	'<th>'.concat(
																		i.escapeHtml(
																			t
																		),
																		'</th>'
																	);
															}
														),
													( a += '</tr>' ),
													o.find( 'thead' ).html( a ),
													( c = '' ),
													n.data &&
														n.data.forEach(
															function ( t, e ) {
																( c += '<tr>' ),
																	t.forEach(
																		function (
																			t
																		) {
																			var e =
																				i.escapeHtml(
																					String(
																						t
																					).substring(
																						0,
																						100
																					)
																				);
																			c +=
																				'<td>'.concat(
																					e,
																					'</td>'
																				);
																		}
																	),
																	( c +=
																		'</tr>' );
															}
														),
													o.find( 'tbody' ).html( c );
											case 13:
											case 'end':
												return e.stop();
										}
								}, e );
							} )
						)();
					},
					buildFieldMapping: function () {
						var t = this;
						if ( this.fileData && this.fileData.columns ) {
							var e = jQuery(
									'input[name="content_type"]:checked'
								).val(),
								r = this.getTargetFields( e ),
								n = jQuery( '.aie-mapping-body' ),
								o = '';
							this.fileData.columns.forEach( function ( e, n ) {
								var a,
									c,
									s =
										( null === ( a = t.fileData.preview ) ||
										void 0 === a ||
										null === ( a = a.data ) ||
										void 0 === a ||
										null === ( a = a[ 0 ] ) ||
										void 0 === a
											? void 0
											: a[ n ] ) || '';
								o += '\n\t\t\t\t<tr>\n\t\t\t\t\t<td><strong>'
									.concat(
										i.escapeHtml( e ),
										'</strong></td>\n\t\t\t\t\t<td>\n\t\t\t\t\t\t<select name="field_map['
									)
									.concat(
										n,
										']" class="regular-text">\n\t\t\t\t\t\t\t<option value="">-- '
									)
									.concat(
										( null === ( c = window.aieData ) ||
										void 0 === c ||
										null === ( c = c.i18n ) ||
										void 0 === c
											? void 0
											: c.skip ) || 'Skip',
										' --</option>\n\t\t\t\t\t\t\t'
									)
									.concat(
										r
											.map( function ( t ) {
												return '<option value="'
													.concat( t.value, '">' )
													.concat(
														t.label,
														'</option>'
													);
											} )
											.join( '' ),
										'\n\t\t\t\t\t\t</select>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td><code>'
									)
									.concat(
										i.escapeHtml(
											String( s ).substring( 0, 50 )
										),
										'</code></td>\n\t\t\t\t</tr>\n\t\t\t'
									);
							} ),
								n.html( o );
						}
					},
					getTargetFields: function ( t ) {
						var e = {
							post: [
								{ value: 'post_title', label: 'Title' },
								{ value: 'post_content', label: 'Content' },
								{ value: 'post_excerpt', label: 'Excerpt' },
								{ value: 'post_status', label: 'Status' },
								{ value: 'post_author', label: 'Author' },
								{ value: 'post_date', label: 'Date' },
								{ value: 'post_name', label: 'Slug' },
								{ value: 'categories', label: 'Categories' },
								{ value: 'tags', label: 'Tags' },
								{
									value: 'featured_image',
									label: 'Featured Image',
								},
							],
							media: [
								{ value: 'post_title', label: 'Title' },
								{ value: 'post_content', label: 'Description' },
								{ value: 'post_excerpt', label: 'Caption' },
								{ value: 'file_url', label: 'File URL' },
								{ value: 'alt_text', label: 'Alt Text' },
							],
						};
						return e[ t ] || e.post;
					},
					autoMapFields: function () {
						jQuery( '.aie-mapping-body select' ).each( function () {
							var t = jQuery( this ),
								e = t
									.closest( 'tr' )
									.find( 'strong' )
									.text()
									.toLowerCase();
							t.find( 'option' ).each( function () {
								var r = jQuery( this ).val().toLowerCase(),
									n = jQuery( this ).text().toLowerCase();
								if (
									e === r ||
									e === n ||
									e.includes( r ) ||
									r.includes( e )
								)
									return t.val( jQuery( this ).val() ), ! 1;
							} );
						} ),
							i.showNotice( 'Auto-mapping completed', 'success' );
					},
					clearFieldMapping: function () {
						jQuery( '.aie-mapping-body select' ).val( '' );
					},
					getFieldMapping: function () {
						var t = {};
						return (
							jQuery( '.aie-mapping-body select' ).each(
								function () {
									var e,
										r = jQuery( this ),
										n =
											null ===
												( e = r
													.attr( 'name' )
													.match( /\[(\d+)\]/ ) ) ||
											void 0 === e
												? void 0
												: e[ 1 ],
										o = r.val();
									o && void 0 !== n && ( t[ n ] = o );
								}
							),
							t
						);
					},
					startImport: function () {
						var t = this;
						return l(
							s().mark( function e() {
								var r, n;
								return s().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( e.prev = 0 ),
														( r = {
															file_path:
																t.fileData
																	.file_path,
															content_type:
																jQuery(
																	'input[name="content_type"]:checked'
																).val(),
															format: t.fileData
																.format,
															field_mapping:
																t.getFieldMapping(),
															duplicate_handling:
																jQuery(
																	'input[name="duplicate_handling"]:checked'
																).val(),
															post_status: jQuery(
																'[name="post_status"]'
															).val(),
															post_type:
																jQuery(
																	'[name="post_type"]'
																).val(),
															download_images:
																jQuery(
																	'[name="download_images"]'
																).is(
																	':checked'
																),
															batch_size:
																parseInt(
																	jQuery(
																		'[name="batch_size"]'
																	).val()
																) || 50,
														} ),
														( e.next = 4 ),
														i.ajax(
															'aie_import_start',
															r
														)
													);
												case 4:
													( n = e.sent ),
														( t.jobId = n.job_id ),
														t.showStep( 6 ),
														t.startProgressTracking(),
														i.showNotice(
															'Import started successfully',
															'success'
														),
														( e.next = 14 );
													break;
												case 11:
													( e.prev = 11 ),
														( e.t0 = e.catch( 0 ) ),
														i.handleError(
															e.t0,
															'Start import'
														);
												case 14:
												case 'end':
													return e.stop();
											}
									},
									e,
									null,
									[ [ 0, 11 ] ]
								);
							} )
						)();
					},
					startProgressTracking: function () {
						var t = this;
						this.progressInterval = setInterval( function () {
							t.updateProgress();
						}, 2e3 );
					},
					updateProgress: function () {
						var t = this;
						return l(
							s().mark( function e() {
								var r;
								return s().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( e.prev = 0 ),
														( e.next = 3 ),
														i.ajax(
															'aie_import_get_progress',
															{ job_id: t.jobId }
														)
													);
												case 3:
													( r = e.sent ),
														i.updateProgressBar(
															jQuery(
																'.aie-step-6'
															),
															r
														),
														'completed' === r.status
															? t.onImportComplete(
																	r
															  )
															: 'failed' ===
																	r.status &&
															  t.onImportFailed(
																	r
															  ),
														( e.next = 11 );
													break;
												case 8:
													( e.prev = 8 ),
														( e.t0 = e.catch( 0 ) ),
														console.error(
															'Progress update error:',
															e.t0
														);
												case 11:
												case 'end':
													return e.stop();
											}
									},
									e,
									null,
									[ [ 0, 8 ] ]
								);
							} )
						)();
					},
					onImportComplete: function ( t ) {
						var e;
						clearInterval( this.progressInterval ),
							jQuery( '.aie-import-results' ).show(),
							jQuery( '.aie-result-processed' ).text(
								t.processed || 0
							),
							jQuery( '.aie-result-success' ).text(
								t.success || 0
							),
							jQuery( '.aie-result-failed' ).text(
								t.failed || 0
							),
							jQuery( '.aie-result-duration' ).text(
								( null === ( e = t.estimates ) || void 0 === e
									? void 0
									: e.elapsed_formatted ) || '0s'
							),
							jQuery( '.aie-cancel-import' ).hide(),
							jQuery( '.aie-new-import' ).show(),
							i.showNotice(
								'Import completed successfully!',
								'success'
							);
					},
					onImportFailed: function ( t ) {
						clearInterval( this.progressInterval ),
							i.showNotice(
								'Import failed: ' +
									( t.error || 'Unknown error' ),
								'error'
							);
					},
					cancelImport: function () {
						var t = this;
						return l(
							s().mark( function e() {
								return s().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													if (
														confirm(
															'Are you sure you want to cancel this import?'
														)
													) {
														e.next = 2;
														break;
													}
													return e.abrupt( 'return' );
												case 2:
													return (
														( e.prev = 2 ),
														( e.next = 5 ),
														i.ajax(
															'aie_import_cancel',
															{ job_id: t.jobId }
														)
													);
												case 5:
													clearInterval(
														t.progressInterval
													),
														i.showNotice(
															'Import cancelled',
															'info'
														),
														t.resetWizard(),
														( e.next = 13 );
													break;
												case 10:
													( e.prev = 10 ),
														( e.t0 = e.catch( 2 ) ),
														i.handleError(
															e.t0,
															'Cancel import'
														);
												case 13:
												case 'end':
													return e.stop();
											}
									},
									e,
									null,
									[ [ 2, 10 ] ]
								);
							} )
						)();
					},
					toggleLogs: function () {
						jQuery( '.aie-logs-container' ).slideToggle();
					},
					resetWizard: function () {
						( this.currentStep = 1 ),
							( this.uploadedFile = null ),
							( this.fileData = null ),
							( this.jobId = null ),
							clearInterval( this.progressInterval ),
							jQuery(
								'#wp-aie-import input[type="text"], #wp-aie-import input[type="file"]'
							).val( '' ),
							jQuery(
								'#wp-aie-import input[type="radio"]:first'
							).prop( 'checked', ! 0 ),
							jQuery( '.aie-file-info' ).hide(),
							jQuery( '.aie-upload-placeholder' ).show(),
							jQuery( '.aie-import-results' ).hide(),
							this.showStep( 1 );
					},
				};
				function f( t ) {
					return (
						( f =
							'function' == typeof Symbol &&
							'symbol' == typeof Symbol.iterator
								? function ( t ) {
										return typeof t;
								  }
								: function ( t ) {
										return t &&
											'function' == typeof Symbol &&
											t.constructor === Symbol &&
											t !== Symbol.prototype
											? 'symbol'
											: typeof t;
								  } ),
						f( t )
					);
				}
				function d() {
					d = function () {
						return e;
					};
					var t,
						e = {},
						r = Object.prototype,
						n = r.hasOwnProperty,
						o =
							Object.defineProperty ||
							function ( t, e, r ) {
								t[ e ] = r.value;
							},
						a = 'function' == typeof Symbol ? Symbol : {},
						i = a.iterator || '@@iterator',
						c = a.asyncIterator || '@@asyncIterator',
						s = a.toStringTag || '@@toStringTag';
					function u( t, e, r ) {
						return (
							Object.defineProperty( t, e, {
								value: r,
								enumerable: ! 0,
								configurable: ! 0,
								writable: ! 0,
							} ),
							t[ e ]
						);
					}
					try {
						u( {}, '' );
					} catch ( t ) {
						u = function ( t, e, r ) {
							return ( t[ e ] = r );
						};
					}
					function l( t, e, r, n ) {
						var a = e && e.prototype instanceof w ? e : w,
							i = Object.create( a.prototype ),
							c = new C( n || [] );
						return o( i, '_invoke', { value: F( t, r, c ) } ), i;
					}
					function p( t, e, r ) {
						try {
							return { type: 'normal', arg: t.call( e, r ) };
						} catch ( t ) {
							return { type: 'throw', arg: t };
						}
					}
					e.wrap = l;
					var h = 'suspendedStart',
						v = 'suspendedYield',
						y = 'executing',
						m = 'completed',
						g = {};
					function w() {}
					function x() {}
					function j() {}
					var b = {};
					u( b, i, function () {
						return this;
					} );
					var _ = Object.getPrototypeOf,
						Q = _ && _( _( P( [] ) ) );
					Q && Q !== r && n.call( Q, i ) && ( b = Q );
					var S = ( j.prototype = w.prototype = Object.create( b ) );
					function k( t ) {
						[ 'next', 'throw', 'return' ].forEach( function ( e ) {
							u( t, e, function ( t ) {
								return this._invoke( e, t );
							} );
						} );
					}
					function E( t, e ) {
						function r( o, a, i, c ) {
							var s = p( t[ o ], t, a );
							if ( 'throw' !== s.type ) {
								var u = s.arg,
									l = u.value;
								return l &&
									'object' == f( l ) &&
									n.call( l, '__await' )
									? e.resolve( l.__await ).then(
											function ( t ) {
												r( 'next', t, i, c );
											},
											function ( t ) {
												r( 'throw', t, i, c );
											}
									  )
									: e.resolve( l ).then(
											function ( t ) {
												( u.value = t ), i( u );
											},
											function ( t ) {
												return r( 'throw', t, i, c );
											}
									  );
							}
							c( s.arg );
						}
						var a;
						o( this, '_invoke', {
							value: function ( t, n ) {
								function o() {
									return new e( function ( e, o ) {
										r( t, n, e, o );
									} );
								}
								return ( a = a ? a.then( o, o ) : o() );
							},
						} );
					}
					function F( e, r, n ) {
						var o = h;
						return function ( a, i ) {
							if ( o === y )
								throw Error( 'Generator is already running' );
							if ( o === m ) {
								if ( 'throw' === a ) throw i;
								return { value: t, done: ! 0 };
							}
							for ( n.method = a, n.arg = i; ;  ) {
								var c = n.delegate;
								if ( c ) {
									var s = L( c, n );
									if ( s ) {
										if ( s === g ) continue;
										return s;
									}
								}
								if ( 'next' === n.method )
									n.sent = n._sent = n.arg;
								else if ( 'throw' === n.method ) {
									if ( o === h ) throw ( ( o = m ), n.arg );
									n.dispatchException( n.arg );
								} else
									'return' === n.method &&
										n.abrupt( 'return', n.arg );
								o = y;
								var u = p( e, r, n );
								if ( 'normal' === u.type ) {
									if (
										( ( o = n.done ? m : v ), u.arg === g )
									)
										continue;
									return { value: u.arg, done: n.done };
								}
								'throw' === u.type &&
									( ( o = m ),
									( n.method = 'throw' ),
									( n.arg = u.arg ) );
							}
						};
					}
					function L( e, r ) {
						var n = r.method,
							o = e.iterator[ n ];
						if ( o === t )
							return (
								( r.delegate = null ),
								( 'throw' === n &&
									e.iterator.return &&
									( ( r.method = 'return' ),
									( r.arg = t ),
									L( e, r ),
									'throw' === r.method ) ) ||
									( 'return' !== n &&
										( ( r.method = 'throw' ),
										( r.arg = new TypeError(
											"The iterator does not provide a '" +
												n +
												"' method"
										) ) ) ),
								g
							);
						var a = p( o, e.iterator, r.arg );
						if ( 'throw' === a.type )
							return (
								( r.method = 'throw' ),
								( r.arg = a.arg ),
								( r.delegate = null ),
								g
							);
						var i = a.arg;
						return i
							? i.done
								? ( ( r[ e.resultName ] = i.value ),
								  ( r.next = e.nextLoc ),
								  'return' !== r.method &&
										( ( r.method = 'next' ),
										( r.arg = t ) ),
								  ( r.delegate = null ),
								  g )
								: i
							: ( ( r.method = 'throw' ),
							  ( r.arg = new TypeError(
									'iterator result is not an object'
							  ) ),
							  ( r.delegate = null ),
							  g );
					}
					function O( t ) {
						var e = { tryLoc: t[ 0 ] };
						1 in t && ( e.catchLoc = t[ 1 ] ),
							2 in t &&
								( ( e.finallyLoc = t[ 2 ] ),
								( e.afterLoc = t[ 3 ] ) ),
							this.tryEntries.push( e );
					}
					function I( t ) {
						var e = t.completion || {};
						( e.type = 'normal' ),
							delete e.arg,
							( t.completion = e );
					}
					function C( t ) {
						( this.tryEntries = [ { tryLoc: 'root' } ] ),
							t.forEach( O, this ),
							this.reset( ! 0 );
					}
					function P( e ) {
						if ( e || '' === e ) {
							var r = e[ i ];
							if ( r ) return r.call( e );
							if ( 'function' == typeof e.next ) return e;
							if ( ! isNaN( e.length ) ) {
								var o = -1,
									a = function r() {
										for ( ; ++o < e.length;  )
											if ( n.call( e, o ) )
												return (
													( r.value = e[ o ] ),
													( r.done = ! 1 ),
													r
												);
										return (
											( r.value = t ), ( r.done = ! 0 ), r
										);
									};
								return ( a.next = a );
							}
						}
						throw new TypeError( f( e ) + ' is not iterable' );
					}
					return (
						( x.prototype = j ),
						o( S, 'constructor', { value: j, configurable: ! 0 } ),
						o( j, 'constructor', { value: x, configurable: ! 0 } ),
						( x.displayName = u( j, s, 'GeneratorFunction' ) ),
						( e.isGeneratorFunction = function ( t ) {
							var e = 'function' == typeof t && t.constructor;
							return (
								!! e &&
								( e === x ||
									'GeneratorFunction' ===
										( e.displayName || e.name ) )
							);
						} ),
						( e.mark = function ( t ) {
							return (
								Object.setPrototypeOf
									? Object.setPrototypeOf( t, j )
									: ( ( t.__proto__ = j ),
									  u( t, s, 'GeneratorFunction' ) ),
								( t.prototype = Object.create( S ) ),
								t
							);
						} ),
						( e.awrap = function ( t ) {
							return { __await: t };
						} ),
						k( E.prototype ),
						u( E.prototype, c, function () {
							return this;
						} ),
						( e.AsyncIterator = E ),
						( e.async = function ( t, r, n, o, a ) {
							void 0 === a && ( a = Promise );
							var i = new E( l( t, r, n, o ), a );
							return e.isGeneratorFunction( r )
								? i
								: i.next().then( function ( t ) {
										return t.done ? t.value : i.next();
								  } );
						} ),
						k( S ),
						u( S, s, 'Generator' ),
						u( S, i, function () {
							return this;
						} ),
						u( S, 'toString', function () {
							return '[object Generator]';
						} ),
						( e.keys = function ( t ) {
							var e = Object( t ),
								r = [];
							for ( var n in e ) r.push( n );
							return (
								r.reverse(),
								function t() {
									for ( ; r.length;  ) {
										var n = r.pop();
										if ( n in e )
											return (
												( t.value = n ),
												( t.done = ! 1 ),
												t
											);
									}
									return ( t.done = ! 0 ), t;
								}
							);
						} ),
						( e.values = P ),
						( C.prototype = {
							constructor: C,
							reset: function ( e ) {
								if (
									( ( this.prev = 0 ),
									( this.next = 0 ),
									( this.sent = this._sent = t ),
									( this.done = ! 1 ),
									( this.delegate = null ),
									( this.method = 'next' ),
									( this.arg = t ),
									this.tryEntries.forEach( I ),
									! e )
								)
									for ( var r in this )
										't' === r.charAt( 0 ) &&
											n.call( this, r ) &&
											! isNaN( +r.slice( 1 ) ) &&
											( this[ r ] = t );
							},
							stop: function () {
								this.done = ! 0;
								var t = this.tryEntries[ 0 ].completion;
								if ( 'throw' === t.type ) throw t.arg;
								return this.rval;
							},
							dispatchException: function ( e ) {
								if ( this.done ) throw e;
								var r = this;
								function o( n, o ) {
									return (
										( c.type = 'throw' ),
										( c.arg = e ),
										( r.next = n ),
										o &&
											( ( r.method = 'next' ),
											( r.arg = t ) ),
										!! o
									);
								}
								for (
									var a = this.tryEntries.length - 1;
									a >= 0;
									--a
								) {
									var i = this.tryEntries[ a ],
										c = i.completion;
									if ( 'root' === i.tryLoc )
										return o( 'end' );
									if ( i.tryLoc <= this.prev ) {
										var s = n.call( i, 'catchLoc' ),
											u = n.call( i, 'finallyLoc' );
										if ( s && u ) {
											if ( this.prev < i.catchLoc )
												return o( i.catchLoc, ! 0 );
											if ( this.prev < i.finallyLoc )
												return o( i.finallyLoc );
										} else if ( s ) {
											if ( this.prev < i.catchLoc )
												return o( i.catchLoc, ! 0 );
										} else {
											if ( ! u )
												throw Error(
													'try statement without catch or finally'
												);
											if ( this.prev < i.finallyLoc )
												return o( i.finallyLoc );
										}
									}
								}
							},
							abrupt: function ( t, e ) {
								for (
									var r = this.tryEntries.length - 1;
									r >= 0;
									--r
								) {
									var o = this.tryEntries[ r ];
									if (
										o.tryLoc <= this.prev &&
										n.call( o, 'finallyLoc' ) &&
										this.prev < o.finallyLoc
									) {
										var a = o;
										break;
									}
								}
								a &&
									( 'break' === t || 'continue' === t ) &&
									a.tryLoc <= e &&
									e <= a.finallyLoc &&
									( a = null );
								var i = a ? a.completion : {};
								return (
									( i.type = t ),
									( i.arg = e ),
									a
										? ( ( this.method = 'next' ),
										  ( this.next = a.finallyLoc ),
										  g )
										: this.complete( i )
								);
							},
							complete: function ( t, e ) {
								if ( 'throw' === t.type ) throw t.arg;
								return (
									'break' === t.type || 'continue' === t.type
										? ( this.next = t.arg )
										: 'return' === t.type
										? ( ( this.rval = this.arg = t.arg ),
										  ( this.method = 'return' ),
										  ( this.next = 'end' ) )
										: 'normal' === t.type &&
										  e &&
										  ( this.next = e ),
									g
								);
							},
							finish: function ( t ) {
								for (
									var e = this.tryEntries.length - 1;
									e >= 0;
									--e
								) {
									var r = this.tryEntries[ e ];
									if ( r.finallyLoc === t )
										return (
											this.complete(
												r.completion,
												r.afterLoc
											),
											I( r ),
											g
										);
								}
							},
							catch: function ( t ) {
								for (
									var e = this.tryEntries.length - 1;
									e >= 0;
									--e
								) {
									var r = this.tryEntries[ e ];
									if ( r.tryLoc === t ) {
										var n = r.completion;
										if ( 'throw' === n.type ) {
											var o = n.arg;
											I( r );
										}
										return o;
									}
								}
								throw Error( 'illegal catch attempt' );
							},
							delegateYield: function ( e, r, n ) {
								return (
									( this.delegate = {
										iterator: P( e ),
										resultName: r,
										nextLoc: n,
									} ),
									'next' === this.method && ( this.arg = t ),
									g
								);
							},
						} ),
						e
					);
				}
				function h( t, e, r, n, o, a, i ) {
					try {
						var c = t[ a ]( i ),
							s = c.value;
					} catch ( t ) {
						return void r( t );
					}
					c.done ? e( s ) : Promise.resolve( s ).then( n, o );
				}
				function v( t ) {
					return function () {
						var e = this,
							r = arguments;
						return new Promise( function ( n, o ) {
							var a = t.apply( e, r );
							function i( t ) {
								h( a, n, o, i, c, 'next', t );
							}
							function c( t ) {
								h( a, n, o, i, c, 'throw', t );
							}
							i( void 0 );
						} );
					};
				}
				const y = {
					currentStep: 1,
					totalSteps: 5,
					jobId: null,
					progressInterval: null,
					init: function () {
						jQuery( '#wp-aie-export' ).length &&
							( this.bindEvents(), this.showStep( 1 ) );
					},
					bindEvents: function () {
						var t = this,
							e = jQuery( '#wp-aie-export' );
						e.on( 'click', '.aie-next-step', function () {
							return t.nextStep();
						} ),
							e.on( 'click', '.aie-prev-step', function () {
								return t.prevStep();
							} ),
							e.on(
								'change',
								'input[name="content_type"]',
								function ( e ) {
									return t.onContentTypeChange( e );
								}
							),
							e.on(
								'change',
								'.aie-export-filters input, .aie-export-filters select',
								i.debounce( function () {
									return t.refreshCount();
								}, 500 )
							),
							e.on( 'click', '.aie-refresh-count', function () {
								return t.refreshCount();
							} ),
							e.on(
								'click',
								'.aie-select-all-fields',
								function () {
									return t.selectAllFields( ! 0 );
								}
							),
							e.on(
								'click',
								'.aie-deselect-all-fields',
								function () {
									return t.selectAllFields( ! 1 );
								}
							),
							e.on(
								'click',
								'.aie-select-common-fields',
								function () {
									return t.selectCommonFields();
								}
							),
							e.on(
								'change',
								'input[name="format"]',
								function ( e ) {
									return t.onFormatChange( e );
								}
							),
							e.on( 'click', '.aie-start-export', function () {
								return t.startExport();
							} ),
							e.on( 'click', '.aie-cancel-export', function () {
								return t.cancelExport();
							} ),
							e.on( 'click', '.aie-download-file', function () {
								return t.downloadFile();
							} ),
							e.on( 'click', '.aie-new-export', function () {
								return t.resetWizard();
							} );
					},
					showStep: function ( t ) {
						var e = jQuery( '#wp-aie-export' );
						e.find( '.aie-step' ).removeClass( 'active' ),
							e
								.find( '.aie-step-'.concat( t ) )
								.addClass( 'active' ),
							e
								.find( '.aie-step-indicator' )
								.removeClass( 'active completed' ),
							e
								.find(
									'.aie-step-indicator[data-step="'.concat(
										t,
										'"]'
									)
								)
								.addClass( 'active' ),
							e
								.find( '.aie-step-indicator[data-step]' )
								.filter( function () {
									return jQuery( this ).data( 'step' ) < t;
								} )
								.addClass( 'completed' ),
							( this.currentStep = t ),
							2 === t && this.refreshCount();
					},
					nextStep: function () {
						this.currentStep < this.totalSteps &&
							this.showStep( this.currentStep + 1 );
					},
					prevStep: function () {
						this.currentStep > 1 &&
							this.showStep( this.currentStep - 1 );
					},
					onContentTypeChange: function ( t ) {
						'media' === jQuery( t.target ).val()
							? ( jQuery( '.aie-post-filters' ).hide(),
							  jQuery( '.aie-media-filters' ).show(),
							  jQuery( '.aie-post-field-group' ).hide(),
							  jQuery( '.aie-media-field-group' ).show() )
							: ( jQuery( '.aie-post-filters' ).show(),
							  jQuery( '.aie-media-filters' ).hide(),
							  jQuery( '.aie-post-field-group' ).show(),
							  jQuery( '.aie-media-field-group' ).hide() );
					},
					refreshCount: function () {
						var t = this;
						return v(
							d().mark( function e() {
								var r, n, o, a;
								return d().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( r = t.getFilters() ),
														( n =
															jQuery(
																'.aie-count-value'
															) ),
														( o = jQuery(
															'.aie-filter-summary .spinner'
														) ).addClass(
															'is-active'
														),
														( e.prev = 4 ),
														( e.next = 7 ),
														i.ajax(
															'aie_export_get_count',
															{
																content_type:
																	jQuery(
																		'input[name="content_type"]:checked'
																	).val(),
																filters: r,
															}
														)
													);
												case 7:
													( a = e.sent ),
														n.text( a.count || 0 ),
														( e.next = 15 );
													break;
												case 11:
													( e.prev = 11 ),
														( e.t0 = e.catch( 4 ) ),
														n.text( '-' ),
														console.error(
															'Count error:',
															e.t0
														);
												case 15:
													return (
														( e.prev = 15 ),
														o.removeClass(
															'is-active'
														),
														e.finish( 15 )
													);
												case 18:
												case 'end':
													return e.stop();
											}
									},
									e,
									null,
									[ [ 4, 11, 15, 18 ] ]
								);
							} )
						)();
					},
					getFilters: function () {
						var t = {},
							e = jQuery(
								'input[name="content_type"]:checked'
							).val();
						return (
							'post' === e
								? ( ( t.post_type =
										jQuery( '[name="post_type"]' ).val() ),
								  ( t.post_status =
										jQuery(
											'[name="post_status[]"]'
										).val() || [] ),
								  ( t.date_from =
										jQuery( '[name="date_from"]' ).val() ),
								  ( t.date_to =
										jQuery( '[name="date_to"]' ).val() ),
								  ( t.author =
										jQuery( '[name="author"]' ).val() ),
								  ( t.category =
										jQuery( '[name="category"]' ).val() ),
								  ( t.tag = jQuery( '[name="tag"]' ).val() ),
								  ( t.search =
										jQuery( '[name="search"]' ).val() ) )
								: 'media' === e &&
								  ( ( t.mime_type =
										jQuery( '[name="mime_type"]' ).val() ),
								  ( t.date_from = jQuery(
										'[name="media_date_from"]'
								  ).val() ),
								  ( t.date_to = jQuery(
										'[name="media_date_to"]'
								  ).val() ) ),
							t
						);
					},
					selectAllFields: function ( t ) {
						jQuery( 'input[name="fields[]"]:visible' ).prop(
							'checked',
							t
						);
					},
					selectCommonFields: function () {
						this.selectAllFields( ! 1 );
						[
							'ID',
							'post_title',
							'post_content',
							'post_status',
						].forEach( function ( t ) {
							jQuery(
								'input[name="fields[]"][value="'.concat(
									t,
									'"]'
								)
							).prop( 'checked', ! 0 );
						} );
					},
					onFormatChange: function ( t ) {
						var e = jQuery( t.target ).val();
						jQuery( '.aie-format-options > div' ).hide(),
							jQuery( '.aie-'.concat( e, '-options' ) ).show();
					},
					getSelectedFields: function () {
						var t = [];
						return (
							jQuery( 'input[name="fields[]"]:checked' ).each(
								function () {
									t.push( jQuery( this ).val() );
								}
							),
							t
						);
					},
					startExport: function () {
						var t = this;
						return v(
							d().mark( function e() {
								var r, n, o;
								return d().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													if (
														0 !==
														( r =
															t.getSelectedFields() )
															.length
													) {
														e.next = 4;
														break;
													}
													return (
														i.showNotice(
															'Please select at least one field to export',
															'error'
														),
														e.abrupt( 'return' )
													);
												case 4:
													return (
														( e.prev = 4 ),
														( n = {
															content_type:
																jQuery(
																	'input[name="content_type"]:checked'
																).val(),
															filters:
																t.getFilters(),
															fields: r,
															format: jQuery(
																'input[name="format"]:checked'
															).val(),
															format_options: {
																csv_delimiter:
																	jQuery(
																		'[name="csv_delimiter"]'
																	).val(),
																csv_encoding:
																	jQuery(
																		'[name="csv_encoding"]'
																	).val(),
																csv_include_header:
																	jQuery(
																		'[name="csv_include_header"]'
																	).is(
																		':checked'
																	),
																json_pretty_print:
																	jQuery(
																		'[name="json_pretty_print"]'
																	).is(
																		':checked'
																	),
																xml_root:
																	jQuery(
																		'[name="xml_root"]'
																	).val(),
																xml_item:
																	jQuery(
																		'[name="xml_item"]'
																	).val(),
															},
														} ),
														( e.next = 8 ),
														i.ajax(
															'aie_export_start',
															n
														)
													);
												case 8:
													( o = e.sent ),
														( t.jobId = o.job_id ),
														t.showStep( 5 ),
														t.startProgressTracking(),
														i.showNotice(
															'Export started successfully',
															'success'
														),
														( e.next = 18 );
													break;
												case 15:
													( e.prev = 15 ),
														( e.t0 = e.catch( 4 ) ),
														i.handleError(
															e.t0,
															'Start export'
														);
												case 18:
												case 'end':
													return e.stop();
											}
									},
									e,
									null,
									[ [ 4, 15 ] ]
								);
							} )
						)();
					},
					startProgressTracking: function () {
						var t = this;
						this.progressInterval = setInterval( function () {
							t.updateProgress();
						}, 2e3 );
					},
					updateProgress: function () {
						var t = this;
						return v(
							d().mark( function e() {
								var r;
								return d().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( e.prev = 0 ),
														( e.next = 3 ),
														i.ajax(
															'aie_export_get_progress',
															{ job_id: t.jobId }
														)
													);
												case 3:
													( r = e.sent ),
														i.updateProgressBar(
															jQuery(
																'.aie-step-5'
															),
															r
														),
														'completed' === r.status
															? t.onExportComplete(
																	r
															  )
															: 'failed' ===
																	r.status &&
															  t.onExportFailed(
																	r
															  ),
														( e.next = 11 );
													break;
												case 8:
													( e.prev = 8 ),
														( e.t0 = e.catch( 0 ) ),
														console.error(
															'Progress update error:',
															e.t0
														);
												case 11:
												case 'end':
													return e.stop();
											}
									},
									e,
									null,
									[ [ 0, 8 ] ]
								);
							} )
						)();
					},
					onExportComplete: function ( t ) {
						var e;
						clearInterval( this.progressInterval ),
							jQuery( '.aie-export-results' ).show(),
							jQuery( '.aie-result-processed' ).text(
								t.processed || 0
							),
							jQuery( '.aie-result-filesize' ).text(
								i.formatFileSize( t.file_size || 0 )
							),
							jQuery( '.aie-result-duration' ).text(
								( null === ( e = t.estimates ) || void 0 === e
									? void 0
									: e.elapsed_formatted ) || '0s'
							),
							jQuery( '.aie-cancel-export' ).hide(),
							jQuery( '.aie-new-export' ).show(),
							i.showNotice(
								'Export completed successfully!',
								'success'
							);
					},
					onExportFailed: function ( t ) {
						clearInterval( this.progressInterval ),
							i.showNotice(
								'Export failed: ' +
									( t.error || 'Unknown error' ),
								'error'
							);
					},
					downloadFile: function () {
						var t = this;
						return v(
							d().mark( function e() {
								var r;
								return d().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( e.prev = 0 ),
														( e.next = 3 ),
														i.ajax(
															'aie_export_download',
															{ job_id: t.jobId }
														)
													);
												case 3:
													( r = e.sent )
														.download_url &&
														i.downloadFile(
															r.download_url,
															r.filename
														),
														( e.next = 10 );
													break;
												case 7:
													( e.prev = 7 ),
														( e.t0 = e.catch( 0 ) ),
														i.handleError(
															e.t0,
															'Download file'
														);
												case 10:
												case 'end':
													return e.stop();
											}
									},
									e,
									null,
									[ [ 0, 7 ] ]
								);
							} )
						)();
					},
					cancelExport: function () {
						var t = this;
						return v(
							d().mark( function e() {
								return d().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													if (
														confirm(
															'Are you sure you want to cancel this export?'
														)
													) {
														e.next = 2;
														break;
													}
													return e.abrupt( 'return' );
												case 2:
													return (
														( e.prev = 2 ),
														( e.next = 5 ),
														i.ajax(
															'aie_export_cancel',
															{ job_id: t.jobId }
														)
													);
												case 5:
													clearInterval(
														t.progressInterval
													),
														i.showNotice(
															'Export cancelled',
															'info'
														),
														t.resetWizard(),
														( e.next = 13 );
													break;
												case 10:
													( e.prev = 10 ),
														( e.t0 = e.catch( 2 ) ),
														i.handleError(
															e.t0,
															'Cancel export'
														);
												case 13:
												case 'end':
													return e.stop();
											}
									},
									e,
									null,
									[ [ 2, 10 ] ]
								);
							} )
						)();
					},
					resetWizard: function () {
						( this.currentStep = 1 ),
							( this.jobId = null ),
							clearInterval( this.progressInterval ),
							jQuery(
								'#wp-aie-export input[type="text"], #wp-aie-export input[type="date"]'
							).val( '' ),
							jQuery(
								'#wp-aie-export input[type="radio"]:first'
							).prop( 'checked', ! 0 ),
							jQuery( '.aie-export-results' ).hide(),
							this.showStep( 1 );
					},
				};
				jQuery( document ).ready( function ( e ) {
					p.init(), y.init(), 'function' == typeof t.init && t.init();
				} );
			},
			205: () => {},
		},
		r = {};
	function n( t ) {
		var o = r[ t ];
		if ( void 0 !== o ) return o.exports;
		var a = ( r[ t ] = { exports: {} } );
		return e[ t ]( a, a.exports, n ), a.exports;
	}
	( n.m = e ),
		( t = [] ),
		( n.O = ( e, r, o, a ) => {
			if ( ! r ) {
				var i = 1 / 0;
				for ( l = 0; l < t.length; l++ ) {
					for (
						var [ r, o, a ] = t[ l ], c = ! 0, s = 0;
						s < r.length;
						s++
					)
						( ! 1 & a || i >= a ) &&
						Object.keys( n.O ).every( ( t ) => n.O[ t ]( r[ s ] ) )
							? r.splice( s--, 1 )
							: ( ( c = ! 1 ), a < i && ( i = a ) );
					if ( c ) {
						t.splice( l--, 1 );
						var u = o();
						void 0 !== u && ( e = u );
					}
				}
				return e;
			}
			a = a || 0;
			for ( var l = t.length; l > 0 && t[ l - 1 ][ 2 ] > a; l-- )
				t[ l ] = t[ l - 1 ];
			t[ l ] = [ r, o, a ];
		} ),
		( n.o = ( t, e ) => Object.prototype.hasOwnProperty.call( t, e ) ),
		( () => {
			var t = { 847: 0, 252: 0 };
			n.O.j = ( e ) => 0 === t[ e ];
			var e = ( e, r ) => {
					var o,
						a,
						[ i, c, s ] = r,
						u = 0;
					if ( i.some( ( e ) => 0 !== t[ e ] ) ) {
						for ( o in c ) n.o( c, o ) && ( n.m[ o ] = c[ o ] );
						if ( s ) var l = s( n );
					}
					for ( e && e( r ); u < i.length; u++ )
						( a = i[ u ] ),
							n.o( t, a ) && t[ a ] && t[ a ][ 0 ](),
							( t[ a ] = 0 );
					return n.O( l );
				},
				r = ( self.webpackChunkboilerplate =
					self.webpackChunkboilerplate || [] );
			r.forEach( e.bind( null, 0 ) ),
				( r.push = e.bind( null, r.push.bind( r ) ) );
		} )(),
		n.O( void 0, [ 252 ], () => n( 463 ) );
	var o = n.O( void 0, [ 252 ], () => n( 205 ) );
	o = n.O( o );
} )();
//# sourceMappingURL=app.js.map
