/*! For license information please see app.js.LICENSE.txt */
( () => {
	'use strict';
	var t,
		e = {
			673: () => {
				function t( t ) {
					var e = document.createElement( 'div' );
					( e.className = 'notice notice-success is-dismissible' ),
						( e.innerHTML = '<p>'.concat( o( t ), '</p>' ) );
					var n = document.querySelector( '.wrap' ) || document.body;
					n.insertBefore( e, n.firstChild ),
						setTimeout( function () {
							e.remove();
						}, 5e3 );
					var r = document.createElement( 'button' );
					( r.type = 'button' ),
						( r.className = 'notice-dismiss' ),
						( r.innerHTML =
							'<span class="screen-reader-text">Dismiss this notice.</span>' ),
						r.addEventListener( 'click', function () {
							e.remove();
						} ),
						e.appendChild( r );
				}
				function e( t ) {
					var e = document.createElement( 'div' );
					( e.className = 'notice notice-error is-dismissible' ),
						( e.innerHTML = '<p>'.concat( o( t ), '</p>' ) );
					var n = document.querySelector( '.wrap' ) || document.body;
					n.insertBefore( e, n.firstChild ),
						setTimeout( function () {
							e.remove();
						}, 1e4 );
					var r = document.createElement( 'button' );
					( r.type = 'button' ),
						( r.className = 'notice-dismiss' ),
						( r.innerHTML =
							'<span class="screen-reader-text">Dismiss this notice.</span>' ),
						r.addEventListener( 'click', function () {
							e.remove();
						} ),
						e.appendChild( r );
				}
				function n( t, e ) {
					e.querySelectorAll( '.aie-modal-error' ).forEach(
						function ( t ) {
							return t.remove();
						}
					);
					var n = document.createElement( 'div' );
					( n.className =
						'notice notice-error is-dismissible aie-modal-error' ),
						( n.style.margin = '10px 0' ),
						( n.innerHTML = '<p>'.concat( o( t ), '</p>' ) );
					var r =
						e.querySelector( '.aie-modal-content' ) ||
						e.querySelector( '.aie-modal-body' ) ||
						e;
					r.firstChild
						? r.insertBefore( n, r.firstChild )
						: r.appendChild( n ),
						setTimeout( function () {
							n.remove();
						}, 1e4 );
					var a = document.createElement( 'button' );
					( a.type = 'button' ),
						( a.className = 'notice-dismiss' ),
						( a.innerHTML =
							'<span class="screen-reader-text">Dismiss this notice.</span>' ),
						a.addEventListener( 'click', function () {
							n.remove();
						} ),
						n.appendChild( a ),
						( r.scrollTop = 0 );
				}
				function r( t ) {
					return new Promise( function ( e ) {
						e( confirm( t ) );
					} );
				}
				function o( t ) {
					var e = document.createElement( 'div' );
					return ( e.textContent = t ), e.innerHTML;
				}
				function a( t ) {
					return (
						( a =
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
						a( t )
					);
				}
				function i( t, e ) {
					return (
						( function ( t ) {
							if ( Array.isArray( t ) ) return t;
						} )( t ) ||
						( function ( t, e ) {
							var n =
								null == t
									? null
									: ( 'undefined' != typeof Symbol &&
											t[ Symbol.iterator ] ) ||
									  t[ '@@iterator' ];
							if ( null != n ) {
								var r,
									o,
									a,
									i,
									c = [],
									s = ! 0,
									u = ! 1;
								try {
									if (
										( ( a = ( n = n.call( t ) ).next ),
										0 === e )
									) {
										if ( Object( n ) !== n ) return;
										s = ! 1;
									} else
										for (
											;
											! ( s = ( r = a.call( n ) )
												.done ) &&
											( c.push( r.value ),
											c.length !== e );
											s = ! 0
										);
								} catch ( t ) {
									( u = ! 0 ), ( o = t );
								} finally {
									try {
										if (
											! s &&
											null != n.return &&
											( ( i = n.return() ),
											Object( i ) !== i )
										)
											return;
									} finally {
										if ( u ) throw o;
									}
								}
								return c;
							}
						} )( t, e ) ||
						( function ( t, e ) {
							if ( t ) {
								if ( 'string' == typeof t ) return c( t, e );
								var n = {}.toString.call( t ).slice( 8, -1 );
								return (
									'Object' === n &&
										t.constructor &&
										( n = t.constructor.name ),
									'Map' === n || 'Set' === n
										? Array.from( t )
										: 'Arguments' === n ||
										  /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
												n
										  )
										? c( t, e )
										: void 0
								);
							}
						} )( t, e ) ||
						( function () {
							throw new TypeError(
								'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
							);
						} )()
					);
				}
				function c( t, e ) {
					( null == e || e > t.length ) && ( e = t.length );
					for ( var n = 0, r = Array( e ); n < e; n++ )
						r[ n ] = t[ n ];
					return r;
				}
				function s() {
					s = function () {
						return e;
					};
					var t,
						e = {},
						n = Object.prototype,
						r = n.hasOwnProperty,
						o =
							Object.defineProperty ||
							function ( t, e, n ) {
								t[ e ] = n.value;
							},
						i = 'function' == typeof Symbol ? Symbol : {},
						c = i.iterator || '@@iterator',
						u = i.asyncIterator || '@@asyncIterator',
						l = i.toStringTag || '@@toStringTag';
					function d( t, e, n ) {
						return (
							Object.defineProperty( t, e, {
								value: n,
								enumerable: ! 0,
								configurable: ! 0,
								writable: ! 0,
							} ),
							t[ e ]
						);
					}
					try {
						d( {}, '' );
					} catch ( t ) {
						d = function ( t, e, n ) {
							return ( t[ e ] = n );
						};
					}
					function p( t, e, n, r ) {
						var a = e && e.prototype instanceof w ? e : w,
							i = Object.create( a.prototype ),
							c = new F( r || [] );
						return o( i, '_invoke', { value: I( t, n, c ) } ), i;
					}
					function f( t, e, n ) {
						try {
							return { type: 'normal', arg: t.call( e, n ) };
						} catch ( t ) {
							return { type: 'throw', arg: t };
						}
					}
					e.wrap = p;
					var h = 'suspendedStart',
						v = 'suspendedYield',
						y = 'executing',
						m = 'completed',
						g = {};
					function w() {}
					function b() {}
					function x() {}
					var E = {};
					d( E, c, function () {
						return this;
					} );
					var j = Object.getPrototypeOf,
						S = j && j( j( O( [] ) ) );
					S && S !== n && r.call( S, c ) && ( E = S );
					var _ = ( x.prototype = w.prototype = Object.create( E ) );
					function L( t ) {
						[ 'next', 'throw', 'return' ].forEach( function ( e ) {
							d( t, e, function ( t ) {
								return this._invoke( e, t );
							} );
						} );
					}
					function k( t, e ) {
						function n( o, i, c, s ) {
							var u = f( t[ o ], t, i );
							if ( 'throw' !== u.type ) {
								var l = u.arg,
									d = l.value;
								return d &&
									'object' == a( d ) &&
									r.call( d, '__await' )
									? e.resolve( d.__await ).then(
											function ( t ) {
												n( 'next', t, c, s );
											},
											function ( t ) {
												n( 'throw', t, c, s );
											}
									  )
									: e.resolve( d ).then(
											function ( t ) {
												( l.value = t ), c( l );
											},
											function ( t ) {
												return n( 'throw', t, c, s );
											}
									  );
							}
							s( u.arg );
						}
						var i;
						o( this, '_invoke', {
							value: function ( t, r ) {
								function o() {
									return new e( function ( e, o ) {
										n( t, r, e, o );
									} );
								}
								return ( i = i ? i.then( o, o ) : o() );
							},
						} );
					}
					function I( e, n, r ) {
						var o = h;
						return function ( a, i ) {
							if ( o === y )
								throw Error( 'Generator is already running' );
							if ( o === m ) {
								if ( 'throw' === a ) throw i;
								return { value: t, done: ! 0 };
							}
							for ( r.method = a, r.arg = i; ;  ) {
								var c = r.delegate;
								if ( c ) {
									var s = P( c, r );
									if ( s ) {
										if ( s === g ) continue;
										return s;
									}
								}
								if ( 'next' === r.method )
									r.sent = r._sent = r.arg;
								else if ( 'throw' === r.method ) {
									if ( o === h ) throw ( ( o = m ), r.arg );
									r.dispatchException( r.arg );
								} else
									'return' === r.method &&
										r.abrupt( 'return', r.arg );
								o = y;
								var u = f( e, n, r );
								if ( 'normal' === u.type ) {
									if (
										( ( o = r.done ? m : v ), u.arg === g )
									)
										continue;
									return { value: u.arg, done: r.done };
								}
								'throw' === u.type &&
									( ( o = m ),
									( r.method = 'throw' ),
									( r.arg = u.arg ) );
							}
						};
					}
					function P( e, n ) {
						var r = n.method,
							o = e.iterator[ r ];
						if ( o === t )
							return (
								( n.delegate = null ),
								( 'throw' === r &&
									e.iterator.return &&
									( ( n.method = 'return' ),
									( n.arg = t ),
									P( e, n ),
									'throw' === n.method ) ) ||
									( 'return' !== r &&
										( ( n.method = 'throw' ),
										( n.arg = new TypeError(
											"The iterator does not provide a '" +
												r +
												"' method"
										) ) ) ),
								g
							);
						var a = f( o, e.iterator, n.arg );
						if ( 'throw' === a.type )
							return (
								( n.method = 'throw' ),
								( n.arg = a.arg ),
								( n.delegate = null ),
								g
							);
						var i = a.arg;
						return i
							? i.done
								? ( ( n[ e.resultName ] = i.value ),
								  ( n.next = e.nextLoc ),
								  'return' !== n.method &&
										( ( n.method = 'next' ),
										( n.arg = t ) ),
								  ( n.delegate = null ),
								  g )
								: i
							: ( ( n.method = 'throw' ),
							  ( n.arg = new TypeError(
									'iterator result is not an object'
							  ) ),
							  ( n.delegate = null ),
							  g );
					}
					function Q( t ) {
						var e = { tryLoc: t[ 0 ] };
						1 in t && ( e.catchLoc = t[ 1 ] ),
							2 in t &&
								( ( e.finallyLoc = t[ 2 ] ),
								( e.afterLoc = t[ 3 ] ) ),
							this.tryEntries.push( e );
					}
					function C( t ) {
						var e = t.completion || {};
						( e.type = 'normal' ),
							delete e.arg,
							( t.completion = e );
					}
					function F( t ) {
						( this.tryEntries = [ { tryLoc: 'root' } ] ),
							t.forEach( Q, this ),
							this.reset( ! 0 );
					}
					function O( e ) {
						if ( e || '' === e ) {
							var n = e[ c ];
							if ( n ) return n.call( e );
							if ( 'function' == typeof e.next ) return e;
							if ( ! isNaN( e.length ) ) {
								var o = -1,
									i = function n() {
										for ( ; ++o < e.length;  )
											if ( r.call( e, o ) )
												return (
													( n.value = e[ o ] ),
													( n.done = ! 1 ),
													n
												);
										return (
											( n.value = t ), ( n.done = ! 0 ), n
										);
									};
								return ( i.next = i );
							}
						}
						throw new TypeError( a( e ) + ' is not iterable' );
					}
					return (
						( b.prototype = x ),
						o( _, 'constructor', { value: x, configurable: ! 0 } ),
						o( x, 'constructor', { value: b, configurable: ! 0 } ),
						( b.displayName = d( x, l, 'GeneratorFunction' ) ),
						( e.isGeneratorFunction = function ( t ) {
							var e = 'function' == typeof t && t.constructor;
							return (
								!! e &&
								( e === b ||
									'GeneratorFunction' ===
										( e.displayName || e.name ) )
							);
						} ),
						( e.mark = function ( t ) {
							return (
								Object.setPrototypeOf
									? Object.setPrototypeOf( t, x )
									: ( ( t.__proto__ = x ),
									  d( t, l, 'GeneratorFunction' ) ),
								( t.prototype = Object.create( _ ) ),
								t
							);
						} ),
						( e.awrap = function ( t ) {
							return { __await: t };
						} ),
						L( k.prototype ),
						d( k.prototype, u, function () {
							return this;
						} ),
						( e.AsyncIterator = k ),
						( e.async = function ( t, n, r, o, a ) {
							void 0 === a && ( a = Promise );
							var i = new k( p( t, n, r, o ), a );
							return e.isGeneratorFunction( n )
								? i
								: i.next().then( function ( t ) {
										return t.done ? t.value : i.next();
								  } );
						} ),
						L( _ ),
						d( _, l, 'Generator' ),
						d( _, c, function () {
							return this;
						} ),
						d( _, 'toString', function () {
							return '[object Generator]';
						} ),
						( e.keys = function ( t ) {
							var e = Object( t ),
								n = [];
							for ( var r in e ) n.push( r );
							return (
								n.reverse(),
								function t() {
									for ( ; n.length;  ) {
										var r = n.pop();
										if ( r in e )
											return (
												( t.value = r ),
												( t.done = ! 1 ),
												t
											);
									}
									return ( t.done = ! 0 ), t;
								}
							);
						} ),
						( e.values = O ),
						( F.prototype = {
							constructor: F,
							reset: function ( e ) {
								if (
									( ( this.prev = 0 ),
									( this.next = 0 ),
									( this.sent = this._sent = t ),
									( this.done = ! 1 ),
									( this.delegate = null ),
									( this.method = 'next' ),
									( this.arg = t ),
									this.tryEntries.forEach( C ),
									! e )
								)
									for ( var n in this )
										't' === n.charAt( 0 ) &&
											r.call( this, n ) &&
											! isNaN( +n.slice( 1 ) ) &&
											( this[ n ] = t );
							},
							stop: function () {
								this.done = ! 0;
								var t = this.tryEntries[ 0 ].completion;
								if ( 'throw' === t.type ) throw t.arg;
								return this.rval;
							},
							dispatchException: function ( e ) {
								if ( this.done ) throw e;
								var n = this;
								function o( r, o ) {
									return (
										( c.type = 'throw' ),
										( c.arg = e ),
										( n.next = r ),
										o &&
											( ( n.method = 'next' ),
											( n.arg = t ) ),
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
										var s = r.call( i, 'catchLoc' ),
											u = r.call( i, 'finallyLoc' );
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
									var n = this.tryEntries.length - 1;
									n >= 0;
									--n
								) {
									var o = this.tryEntries[ n ];
									if (
										o.tryLoc <= this.prev &&
										r.call( o, 'finallyLoc' ) &&
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
									var n = this.tryEntries[ e ];
									if ( n.finallyLoc === t )
										return (
											this.complete(
												n.completion,
												n.afterLoc
											),
											C( n ),
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
									var n = this.tryEntries[ e ];
									if ( n.tryLoc === t ) {
										var r = n.completion;
										if ( 'throw' === r.type ) {
											var o = r.arg;
											C( n );
										}
										return o;
									}
								}
								throw Error( 'illegal catch attempt' );
							},
							delegateYield: function ( e, n, r ) {
								return (
									( this.delegate = {
										iterator: O( e ),
										resultName: n,
										nextLoc: r,
									} ),
									'next' === this.method && ( this.arg = t ),
									g
								);
							},
						} ),
						e
					);
				}
				function u( t, e, n, r, o, a, i ) {
					try {
						var c = t[ a ]( i ),
							s = c.value;
					} catch ( t ) {
						return void n( t );
					}
					c.done ? e( s ) : Promise.resolve( s ).then( r, o );
				}
				function l( t ) {
					return function () {
						var e = this,
							n = arguments;
						return new Promise( function ( r, o ) {
							var a = t.apply( e, n );
							function i( t ) {
								u( a, r, o, i, c, 'next', t );
							}
							function c( t ) {
								u( a, r, o, i, c, 'throw', t );
							}
							i( void 0 );
						} );
					};
				}
				const d = {
					functionsModule: null,
					allSnippets: {},
					categories: {},
					currentCategory: '',
					currentSnippet: null,
					init: function ( t ) {
						this.functionsModule = t;
					},
					openLibrary: function () {
						var t = this;
						return l(
							s().mark( function e() {
								var n;
								return s().wrap( function ( e ) {
									for (;;)
										switch ( ( e.prev = e.next ) ) {
											case 0:
												if (
													( n =
														document.getElementById(
															'aie-snippets-library-modal'
														) )
												) {
													e.next = 3;
													break;
												}
												return e.abrupt( 'return' );
											case 3:
												if (
													( ( n.style.display =
														'flex' ),
													( document.body.style.overflow =
														'hidden' ),
													0 !==
														Object.keys(
															t.allSnippets
														).length )
												) {
													e.next = 10;
													break;
												}
												return (
													( e.next = 8 ),
													t.loadSnippets()
												);
											case 8:
												e.next = 11;
												break;
											case 10:
												t.renderSnippets();
											case 11:
												t.bindLibraryEvents();
											case 12:
											case 'end':
												return e.stop();
										}
								}, e );
							} )
						)();
					},
					bindLibraryEvents: function () {
						var t = this;
						document
							.querySelectorAll( '.aie-category-item' )
							.forEach( function ( e ) {
								e.addEventListener( 'click', function ( e ) {
									var n = e.currentTarget.dataset.category;
									t.filterByCategory( n );
								} );
							} );
						var e,
							n = document.getElementById( 'aie-snippet-search' );
						n &&
							n.addEventListener( 'input', function ( n ) {
								clearTimeout( e ),
									( e = setTimeout( function () {
										t.searchSnippets( n.target.value );
									}, 300 ) );
							} );
						var r,
							o,
							a = document.getElementById(
								'aie-snippet-preview-modal'
							);
						a &&
							( null ===
								( r = a.querySelector( '.aie-use-snippet' ) ) ||
								void 0 === r ||
								r.addEventListener( 'click', function () {
									t.importSnippet( t.currentSnippet, ! 1 );
								} ),
							null ===
								( o = a.querySelector(
									'.aie-customize-snippet'
								) ) ||
								void 0 === o ||
								o.addEventListener( 'click', function () {
									t.importSnippet( t.currentSnippet, ! 0 );
								} ) );
					},
					loadSnippets: function () {
						var t = arguments,
							e = this;
						return l(
							s().mark( function n() {
								var r, o, a, i, c, u, l;
								return s().wrap(
									function ( n ) {
										for (;;)
											switch ( ( n.prev = n.next ) ) {
												case 0:
													if (
														( ( o =
															t.length > 0 &&
															void 0 !== t[ 0 ]
																? t[ 0 ]
																: '' ),
														( a =
															document.getElementById(
																'aie-snippets-grid'
															) ) )
													) {
														n.next = 4;
														break;
													}
													return n.abrupt( 'return' );
												case 4:
													return (
														( a.innerHTML =
															'\n\t\t\t<div class="aie-loading-snippets">\n\t\t\t\t<span class="spinner is-active"></span>\n\t\t\t\t<p>'.concat(
																( null ===
																	( r =
																		window.aieData ) ||
																void 0 === r ||
																null ===
																	( r =
																		r.i18n ) ||
																void 0 === r
																	? void 0
																	: r.loading ) ||
																	'Loading snippets...',
																'</p>\n\t\t\t</div>\n\t\t'
															) ),
														( n.prev = 5 ),
														( n.next = 8 ),
														fetch(
															window.aieData
																.ajaxUrl,
															{
																method: 'POST',
																headers: {
																	'Content-Type':
																		'application/x-www-form-urlencoded',
																},
																body: new URLSearchParams(
																	{
																		action: 'aie_functions_get_snippets',
																		nonce:
																			( null ===
																				( i =
																					window.aieData ) ||
																			void 0 ===
																				i
																				? void 0
																				: i.nonce ) ||
																			'',
																		category:
																			o,
																	}
																),
															}
														)
													);
												case 8:
													return (
														( c = n.sent ),
														( n.next = 11 ),
														c.json()
													);
												case 11:
													if (
														( u = n.sent ).success
													) {
														n.next = 14;
														break;
													}
													throw new Error(
														( null ===
															( l = u.data ) ||
														void 0 === l
															? void 0
															: l.message ) ||
															'Failed to load snippets'
													);
												case 14:
													( e.allSnippets =
														u.data.snippets || {} ),
														( e.categories =
															u.data.categories ||
															{} ),
														e.renderCategories(),
														e.renderSnippets(),
														( n.next = 24 );
													break;
												case 20:
													( n.prev = 20 ),
														( n.t0 = n.catch( 5 ) ),
														console.error(
															'Error loading snippets:',
															n.t0
														),
														( a.innerHTML =
															'\n\t\t\t\t<div class="aie-error-message">\n\t\t\t\t\t<span class="dashicons dashicons-warning"></span>\n\t\t\t\t\t<p>'.concat(
																n.t0.message,
																'</p>\n\t\t\t\t</div>\n\t\t\t'
															) );
												case 24:
												case 'end':
													return n.stop();
											}
									},
									n,
									null,
									[ [ 5, 20 ] ]
								);
							} )
						)();
					},
					renderCategories: function () {
						var t,
							e = this,
							n = document.getElementById(
								'aie-categories-list'
							);
						if ( n ) {
							var r = Object.keys( this.allSnippets ).length,
								o = '\n\t\t\t<li class="aie-category-item '
									.concat(
										'' === this.currentCategory
											? 'active'
											: '',
										'" data-category="">\n\t\t\t\t<span class="dashicons dashicons-category"></span>\n\t\t\t\t<span class="aie-category-name">'
									)
									.concat(
										( null === ( t = window.aieData ) ||
										void 0 === t ||
										null === ( t = t.i18n ) ||
										void 0 === t
											? void 0
											: t.all_snippets ) ||
											'All Snippets',
										'</span>\n\t\t\t\t<span class="aie-category-count">'
									)
									.concat( r, '</span>\n\t\t\t</li>\n\t\t' );
							Object.entries( this.categories ).forEach(
								function ( t ) {
									var n = i( t, 2 ),
										r = n[ 0 ],
										a = n[ 1 ],
										c = Object.values(
											e.allSnippets
										).filter( function ( t ) {
											return t.category === r;
										} ).length,
										s = e.currentCategory === r;
									o +=
										'\n\t\t\t\t<li class="aie-category-item '
											.concat(
												s ? 'active' : '',
												'" data-category="'
											)
											.concat(
												r,
												'">\n\t\t\t\t\t<span class="dashicons dashicons-'
											)
											.concat(
												a.icon,
												'"></span>\n\t\t\t\t\t<span class="aie-category-name">'
											)
											.concat(
												a.name,
												'</span>\n\t\t\t\t\t<span class="aie-category-count">'
											)
											.concat(
												c,
												'</span>\n\t\t\t\t</li>\n\t\t\t'
											);
								}
							),
								( n.innerHTML = o );
						}
					},
					renderSnippets: function () {
						var t,
							e = this,
							n = document.getElementById( 'aie-snippets-grid' );
						if ( n ) {
							var r = Object.entries( this.allSnippets );
							if (
								( this.currentCategory &&
									( r = r.filter( function ( t ) {
										return (
											i( t, 2 )[ 1 ].category ===
											e.currentCategory
										);
									} ) ),
								0 !== r.length )
							) {
								var o =
										( null === ( t = window.aieData ) ||
										void 0 === t
											? void 0
											: t.currentPage ) || '',
									a = [
										'wp-advanced-import-export',
										'wp-aie-export',
										'wp-aie-content-sync',
									].includes( o );
								( n.innerHTML = r
									.map( function ( t ) {
										var n,
											r,
											o = i( t, 2 ),
											c = o[ 0 ],
											s = o[ 1 ];
										return '\n\t\t\t<div class="aie-snippet-card" data-snippet-key="'
											.concat(
												c,
												'">\n\t\t\t\t<div class="aie-snippet-header">\n\t\t\t\t\t<h3 class="aie-snippet-name">'
											)
											.concat(
												e.escapeHtml( s.name ),
												'</h3>\n\t\t\t\t\t<span class="aie-snippet-category-badge">'
											)
											.concat(
												e.getCategoryLabel(
													s.category
												),
												'</span>\n\t\t\t\t</div>\n\t\t\t\t<p class="aie-snippet-description">'
											)
											.concat(
												e.escapeHtml( s.description ),
												'</p>\n\t\t\t\t<div class="aie-snippet-tags">\n\t\t\t\t\t'
											)
											.concat(
												s.tags
													? s.tags
															.map(
																function ( t ) {
																	return '<span class="aie-tag">'.concat(
																		e.escapeHtml(
																			t
																		),
																		'</span>'
																	);
																}
															)
															.join( '' )
													: '',
												'\n\t\t\t\t</div>\n\t\t\t\t<div class="aie-snippet-actions">\n\t\t\t\t\t<button type="button" class="button button-small aie-preview-snippet" data-snippet-key="'
											)
											.concat(
												c,
												'">\n\t\t\t\t\t\t<span class="dashicons dashicons-visibility"></span>\n\t\t\t\t\t\t'
											)
											.concat(
												( null ===
													( n = window.aieData ) ||
												void 0 === n ||
												null === ( n = n.i18n ) ||
												void 0 === n
													? void 0
													: n.preview ) || 'Preview',
												'\n\t\t\t\t\t</button>\n\t\t\t\t\t'
											)
											.concat(
												a
													? '<button type="button" class="button button-primary button-small aie-quick-import" data-snippet-key="'
															.concat(
																c,
																'">\n\t\t\t\t\t\t<span class="dashicons dashicons-plus"></span>\n\t\t\t\t\t\t'
															)
															.concat(
																( null ===
																	( r =
																		window.aieData ) ||
																void 0 === r ||
																null ===
																	( r =
																		r.i18n ) ||
																void 0 === r
																	? void 0
																	: r.use ) ||
																	'Use',
																'\n\t\t\t\t\t</button>'
															)
													: '',
												'\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t'
											);
									} )
									.join( '' ) ),
									n
										.querySelectorAll(
											'.aie-preview-snippet'
										)
										.forEach( function ( t ) {
											t.addEventListener(
												'click',
												function ( t ) {
													var n =
														t.currentTarget.dataset
															.snippetKey;
													e.previewSnippet( n );
												}
											);
										} ),
									n
										.querySelectorAll( '.aie-quick-import' )
										.forEach( function ( t ) {
											t.addEventListener(
												'click',
												function ( t ) {
													var n =
														t.currentTarget.dataset
															.snippetKey;
													e.importSnippet( n, ! 1 );
												}
											);
										} );
							} else {
								var c;
								n.innerHTML =
									'\n\t\t\t\t<div class="aie-no-snippets">\n\t\t\t\t\t<span class="dashicons dashicons-info" style="width: auto; height: auto;"></span>\n\t\t\t\t\t<p>'.concat(
										( null === ( c = window.aieData ) ||
										void 0 === c ||
										null === ( c = c.i18n ) ||
										void 0 === c
											? void 0
											: c.no_snippets ) ||
											'No snippets found',
										'</p>\n\t\t\t\t</div>\n\t\t\t'
									);
							}
						}
					},
					filterByCategory: function ( t ) {
						( this.currentCategory = t ),
							document
								.querySelectorAll( '.aie-category-item' )
								.forEach( function ( e ) {
									e.classList.toggle(
										'active',
										e.dataset.category === t
									);
								} ),
							this.renderSnippets();
					},
					searchSnippets: function ( t ) {
						var n = this;
						return l(
							s().mark( function r() {
								var o, a, i, c, u, l, d;
								return s().wrap(
									function ( r ) {
										for (;;)
											switch ( ( r.prev = r.next ) ) {
												case 0:
													if (
														( a =
															document.getElementById(
																'aie-snippets-grid'
															) )
													) {
														r.next = 3;
														break;
													}
													return r.abrupt( 'return' );
												case 3:
													if ( t.trim() ) {
														r.next = 6;
														break;
													}
													return (
														n.renderSnippets(),
														r.abrupt( 'return' )
													);
												case 6:
													return (
														( a.innerHTML =
															'\n\t\t\t<div class="aie-loading-snippets">\n\t\t\t\t<span class="spinner is-active"></span>\n\t\t\t\t<p>'.concat(
																( null ===
																	( o =
																		window.aieData ) ||
																void 0 === o ||
																null ===
																	( o =
																		o.i18n ) ||
																void 0 === o
																	? void 0
																	: o.searching ) ||
																	'Searching...',
																'</p>\n\t\t\t</div>\n\t\t'
															) ),
														( r.prev = 7 ),
														( r.next = 10 ),
														fetch(
															window.aieData
																.ajaxUrl,
															{
																method: 'POST',
																headers: {
																	'Content-Type':
																		'application/x-www-form-urlencoded',
																},
																body: new URLSearchParams(
																	{
																		action: 'aie_functions_search',
																		nonce:
																			( null ===
																				( i =
																					window.aieData ) ||
																			void 0 ===
																				i
																				? void 0
																				: i.nonce ) ||
																			'',
																		query: t,
																	}
																),
															}
														)
													);
												case 10:
													return (
														( c = r.sent ),
														( r.next = 13 ),
														c.json()
													);
												case 13:
													if (
														( u = r.sent ).success
													) {
														r.next = 16;
														break;
													}
													throw new Error(
														( null ===
															( l = u.data ) ||
														void 0 === l
															? void 0
															: l.message ) ||
															'Search failed'
													);
												case 16:
													( d = n.allSnippets ),
														( n.allSnippets =
															u.data.snippets ||
															{} ),
														( n.currentCategory =
															'' ),
														n.renderSnippets(),
														( n.allSnippets = d ),
														( r.next = 27 );
													break;
												case 23:
													( r.prev = 23 ),
														( r.t0 = r.catch( 7 ) ),
														console.error(
															'Error searching snippets:',
															r.t0
														),
														e( r.t0.message );
												case 27:
												case 'end':
													return r.stop();
											}
									},
									r,
									null,
									[ [ 7, 23 ] ]
								);
							} )
						)();
					},
					previewSnippet: function ( t ) {
						var e = this.allSnippets[ t ];
						if ( e ) {
							this.currentSnippet = t;
							var n = document.getElementById(
								'aie-snippet-preview-modal'
							);
							if ( n ) {
								( n.querySelector(
									'.aie-snippet-title'
								).textContent = e.name ),
									( n.querySelector(
										'.aie-snippet-description'
									).textContent = e.description ),
									( n.querySelector(
										'.aie-snippet-category'
									).textContent = this.getCategoryLabel(
										e.category
									) ),
									( n.querySelector(
										'.aie-snippet-code'
									).textContent = e.code ),
									e.tags && e.tags.length > 0
										? ( n.querySelector(
												'.aie-snippet-tags'
										  ).textContent = e.tags.join( ', ' ) )
										: ( n.querySelector(
												'.aie-snippet-tags'
										  ).textContent = 'None' ),
									e.example &&
										( ( n.querySelector(
											'.aie-example-input-value'
										).textContent =
											void 0 !== e.example.input
												? e.example.input
												: 'N/A' ),
										( n.querySelector(
											'.aie-example-output-value'
										).textContent =
											void 0 !== e.example.output
												? e.example.output
												: 'N/A' ) );
								var r = n.querySelector( '.aie-use-snippet' );
								if ( r ) {
									var o,
										a =
											( null === ( o = window.aieData ) ||
											void 0 === o
												? void 0
												: o.currentPage ) || '';
									r.style.display = [
										'wp-advanced-import-export',
										'wp-aie-export',
										'wp-aie-content-sync',
									].includes( a )
										? ''
										: 'none';
								}
								n.style.display = 'flex';
							}
						}
					},
					importSnippet: function ( n ) {
						var r = arguments,
							o = this;
						return l(
							s().mark( function a() {
								var i, c, u, l, d, p, f, h, v, y, m, g;
								return s().wrap(
									function ( a ) {
										for (;;)
											switch ( ( a.prev = a.next ) ) {
												case 0:
													if (
														( ( i =
															r.length > 1 &&
															void 0 !== r[ 1 ] &&
															r[ 1 ] ),
														( c =
															o.allSnippets[
																n
															] ) )
													) {
														a.next = 4;
														break;
													}
													return a.abrupt( 'return' );
												case 4:
													if ( ! i ) {
														a.next = 14;
														break;
													}
													( u =
														document.getElementById(
															'aie-snippets-library-modal'
														) ),
														( l =
															document.getElementById(
																'aie-snippet-preview-modal'
															) ),
														u &&
															( u.style.display =
																'none' ),
														l &&
															( l.style.display =
																'none' ),
														( document.body.style.overflow =
															'' ),
														( d =
															document.getElementById(
																'aie-function-editor-modal'
															) ) &&
															( ( document.getElementById(
																'aie-function-id'
															).value = '' ),
															( document.getElementById(
																'aie-function-name'
															).value = c.name ),
															( document.getElementById(
																'aie-function-description'
															).value =
																c.description ),
															( document.getElementById(
																'aie-function-category'
															).value =
																c.category ),
															( document.getElementById(
																'aie-function-code'
															).value = c.code ),
															( document.getElementById(
																'aie-function-status'
															).value =
																'active' ),
															( document.querySelector(
																'.aie-modal-title'
															).textContent =
																'Customize Function' ),
															( d.style.display =
																'flex' ),
															( document.body.style.overflow =
																'hidden' ) ),
														( a.next = 36 );
													break;
												case 14:
													return (
														( a.prev = 14 ),
														( a.next = 17 ),
														fetch(
															window.aieData
																.ajaxUrl,
															{
																method: 'POST',
																headers: {
																	'Content-Type':
																		'application/x-www-form-urlencoded',
																},
																body: new URLSearchParams(
																	{
																		action: 'aie_functions_import',
																		nonce:
																			( null ===
																				( p =
																					window.aieData ) ||
																			void 0 ===
																				p
																				? void 0
																				: p.nonce ) ||
																			'',
																		snippet_key:
																			n,
																	}
																),
															}
														)
													);
												case 17:
													return (
														( h = a.sent ),
														( a.next = 20 ),
														h.json()
													);
												case 20:
													if (
														( v = a.sent ).success
													) {
														a.next = 23;
														break;
													}
													throw new Error(
														( null ===
															( y = v.data ) ||
														void 0 === y
															? void 0
															: y.message ) ||
															'Import failed'
													);
												case 23:
													t(
														( null ===
															( f =
																window.aieData ) ||
														void 0 === f ||
														null ===
															( f = f.i18n ) ||
														void 0 === f
															? void 0
															: f.snippet_imported ) ||
															'Snippet imported successfully'
													),
														( m =
															document.getElementById(
																'aie-snippets-library-modal'
															) ),
														( g =
															document.getElementById(
																'aie-snippet-preview-modal'
															) ),
														m &&
															( m.style.display =
																'none' ),
														g &&
															( g.style.display =
																'none' ),
														( document.body.style.overflow =
															'' ),
														o.functionsModule &&
															o.functionsModule.loadFunctions(),
														( a.next = 36 );
													break;
												case 32:
													( a.prev = 32 ),
														( a.t0 =
															a.catch( 14 ) ),
														console.error(
															'Error importing snippet:',
															a.t0
														),
														e( a.t0.message );
												case 36:
												case 'end':
													return a.stop();
											}
									},
									a,
									null,
									[ [ 14, 32 ] ]
								);
							} )
						)();
					},
					getCategoryLabel: function ( t ) {
						return (
							{
								string: 'String Operations',
								date: 'Date & Time',
								numeric: 'Numeric Operations',
								html: 'HTML Operations',
								wordpress: 'WordPress',
								validation: 'Validation',
								advanced: 'Advanced',
								custom: 'Custom',
							}[ t ] || t
						);
					},
					escapeHtml: function ( t ) {
						var e = document.createElement( 'div' );
						return ( e.textContent = t ), e.innerHTML;
					},
				};
				function p( t ) {
					return (
						( p =
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
						p( t )
					);
				}
				function f() {
					f = function () {
						return e;
					};
					var t,
						e = {},
						n = Object.prototype,
						r = n.hasOwnProperty,
						o =
							Object.defineProperty ||
							function ( t, e, n ) {
								t[ e ] = n.value;
							},
						a = 'function' == typeof Symbol ? Symbol : {},
						i = a.iterator || '@@iterator',
						c = a.asyncIterator || '@@asyncIterator',
						s = a.toStringTag || '@@toStringTag';
					function u( t, e, n ) {
						return (
							Object.defineProperty( t, e, {
								value: n,
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
						u = function ( t, e, n ) {
							return ( t[ e ] = n );
						};
					}
					function l( t, e, n, r ) {
						var a = e && e.prototype instanceof w ? e : w,
							i = Object.create( a.prototype ),
							c = new F( r || [] );
						return o( i, '_invoke', { value: I( t, n, c ) } ), i;
					}
					function d( t, e, n ) {
						try {
							return { type: 'normal', arg: t.call( e, n ) };
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
					function b() {}
					function x() {}
					var E = {};
					u( E, i, function () {
						return this;
					} );
					var j = Object.getPrototypeOf,
						S = j && j( j( O( [] ) ) );
					S && S !== n && r.call( S, i ) && ( E = S );
					var _ = ( x.prototype = w.prototype = Object.create( E ) );
					function L( t ) {
						[ 'next', 'throw', 'return' ].forEach( function ( e ) {
							u( t, e, function ( t ) {
								return this._invoke( e, t );
							} );
						} );
					}
					function k( t, e ) {
						function n( o, a, i, c ) {
							var s = d( t[ o ], t, a );
							if ( 'throw' !== s.type ) {
								var u = s.arg,
									l = u.value;
								return l &&
									'object' == p( l ) &&
									r.call( l, '__await' )
									? e.resolve( l.__await ).then(
											function ( t ) {
												n( 'next', t, i, c );
											},
											function ( t ) {
												n( 'throw', t, i, c );
											}
									  )
									: e.resolve( l ).then(
											function ( t ) {
												( u.value = t ), i( u );
											},
											function ( t ) {
												return n( 'throw', t, i, c );
											}
									  );
							}
							c( s.arg );
						}
						var a;
						o( this, '_invoke', {
							value: function ( t, r ) {
								function o() {
									return new e( function ( e, o ) {
										n( t, r, e, o );
									} );
								}
								return ( a = a ? a.then( o, o ) : o() );
							},
						} );
					}
					function I( e, n, r ) {
						var o = h;
						return function ( a, i ) {
							if ( o === y )
								throw Error( 'Generator is already running' );
							if ( o === m ) {
								if ( 'throw' === a ) throw i;
								return { value: t, done: ! 0 };
							}
							for ( r.method = a, r.arg = i; ;  ) {
								var c = r.delegate;
								if ( c ) {
									var s = P( c, r );
									if ( s ) {
										if ( s === g ) continue;
										return s;
									}
								}
								if ( 'next' === r.method )
									r.sent = r._sent = r.arg;
								else if ( 'throw' === r.method ) {
									if ( o === h ) throw ( ( o = m ), r.arg );
									r.dispatchException( r.arg );
								} else
									'return' === r.method &&
										r.abrupt( 'return', r.arg );
								o = y;
								var u = d( e, n, r );
								if ( 'normal' === u.type ) {
									if (
										( ( o = r.done ? m : v ), u.arg === g )
									)
										continue;
									return { value: u.arg, done: r.done };
								}
								'throw' === u.type &&
									( ( o = m ),
									( r.method = 'throw' ),
									( r.arg = u.arg ) );
							}
						};
					}
					function P( e, n ) {
						var r = n.method,
							o = e.iterator[ r ];
						if ( o === t )
							return (
								( n.delegate = null ),
								( 'throw' === r &&
									e.iterator.return &&
									( ( n.method = 'return' ),
									( n.arg = t ),
									P( e, n ),
									'throw' === n.method ) ) ||
									( 'return' !== r &&
										( ( n.method = 'throw' ),
										( n.arg = new TypeError(
											"The iterator does not provide a '" +
												r +
												"' method"
										) ) ) ),
								g
							);
						var a = d( o, e.iterator, n.arg );
						if ( 'throw' === a.type )
							return (
								( n.method = 'throw' ),
								( n.arg = a.arg ),
								( n.delegate = null ),
								g
							);
						var i = a.arg;
						return i
							? i.done
								? ( ( n[ e.resultName ] = i.value ),
								  ( n.next = e.nextLoc ),
								  'return' !== n.method &&
										( ( n.method = 'next' ),
										( n.arg = t ) ),
								  ( n.delegate = null ),
								  g )
								: i
							: ( ( n.method = 'throw' ),
							  ( n.arg = new TypeError(
									'iterator result is not an object'
							  ) ),
							  ( n.delegate = null ),
							  g );
					}
					function Q( t ) {
						var e = { tryLoc: t[ 0 ] };
						1 in t && ( e.catchLoc = t[ 1 ] ),
							2 in t &&
								( ( e.finallyLoc = t[ 2 ] ),
								( e.afterLoc = t[ 3 ] ) ),
							this.tryEntries.push( e );
					}
					function C( t ) {
						var e = t.completion || {};
						( e.type = 'normal' ),
							delete e.arg,
							( t.completion = e );
					}
					function F( t ) {
						( this.tryEntries = [ { tryLoc: 'root' } ] ),
							t.forEach( Q, this ),
							this.reset( ! 0 );
					}
					function O( e ) {
						if ( e || '' === e ) {
							var n = e[ i ];
							if ( n ) return n.call( e );
							if ( 'function' == typeof e.next ) return e;
							if ( ! isNaN( e.length ) ) {
								var o = -1,
									a = function n() {
										for ( ; ++o < e.length;  )
											if ( r.call( e, o ) )
												return (
													( n.value = e[ o ] ),
													( n.done = ! 1 ),
													n
												);
										return (
											( n.value = t ), ( n.done = ! 0 ), n
										);
									};
								return ( a.next = a );
							}
						}
						throw new TypeError( p( e ) + ' is not iterable' );
					}
					return (
						( b.prototype = x ),
						o( _, 'constructor', { value: x, configurable: ! 0 } ),
						o( x, 'constructor', { value: b, configurable: ! 0 } ),
						( b.displayName = u( x, s, 'GeneratorFunction' ) ),
						( e.isGeneratorFunction = function ( t ) {
							var e = 'function' == typeof t && t.constructor;
							return (
								!! e &&
								( e === b ||
									'GeneratorFunction' ===
										( e.displayName || e.name ) )
							);
						} ),
						( e.mark = function ( t ) {
							return (
								Object.setPrototypeOf
									? Object.setPrototypeOf( t, x )
									: ( ( t.__proto__ = x ),
									  u( t, s, 'GeneratorFunction' ) ),
								( t.prototype = Object.create( _ ) ),
								t
							);
						} ),
						( e.awrap = function ( t ) {
							return { __await: t };
						} ),
						L( k.prototype ),
						u( k.prototype, c, function () {
							return this;
						} ),
						( e.AsyncIterator = k ),
						( e.async = function ( t, n, r, o, a ) {
							void 0 === a && ( a = Promise );
							var i = new k( l( t, n, r, o ), a );
							return e.isGeneratorFunction( n )
								? i
								: i.next().then( function ( t ) {
										return t.done ? t.value : i.next();
								  } );
						} ),
						L( _ ),
						u( _, s, 'Generator' ),
						u( _, i, function () {
							return this;
						} ),
						u( _, 'toString', function () {
							return '[object Generator]';
						} ),
						( e.keys = function ( t ) {
							var e = Object( t ),
								n = [];
							for ( var r in e ) n.push( r );
							return (
								n.reverse(),
								function t() {
									for ( ; n.length;  ) {
										var r = n.pop();
										if ( r in e )
											return (
												( t.value = r ),
												( t.done = ! 1 ),
												t
											);
									}
									return ( t.done = ! 0 ), t;
								}
							);
						} ),
						( e.values = O ),
						( F.prototype = {
							constructor: F,
							reset: function ( e ) {
								if (
									( ( this.prev = 0 ),
									( this.next = 0 ),
									( this.sent = this._sent = t ),
									( this.done = ! 1 ),
									( this.delegate = null ),
									( this.method = 'next' ),
									( this.arg = t ),
									this.tryEntries.forEach( C ),
									! e )
								)
									for ( var n in this )
										't' === n.charAt( 0 ) &&
											r.call( this, n ) &&
											! isNaN( +n.slice( 1 ) ) &&
											( this[ n ] = t );
							},
							stop: function () {
								this.done = ! 0;
								var t = this.tryEntries[ 0 ].completion;
								if ( 'throw' === t.type ) throw t.arg;
								return this.rval;
							},
							dispatchException: function ( e ) {
								if ( this.done ) throw e;
								var n = this;
								function o( r, o ) {
									return (
										( c.type = 'throw' ),
										( c.arg = e ),
										( n.next = r ),
										o &&
											( ( n.method = 'next' ),
											( n.arg = t ) ),
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
										var s = r.call( i, 'catchLoc' ),
											u = r.call( i, 'finallyLoc' );
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
									var n = this.tryEntries.length - 1;
									n >= 0;
									--n
								) {
									var o = this.tryEntries[ n ];
									if (
										o.tryLoc <= this.prev &&
										r.call( o, 'finallyLoc' ) &&
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
									var n = this.tryEntries[ e ];
									if ( n.finallyLoc === t )
										return (
											this.complete(
												n.completion,
												n.afterLoc
											),
											C( n ),
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
									var n = this.tryEntries[ e ];
									if ( n.tryLoc === t ) {
										var r = n.completion;
										if ( 'throw' === r.type ) {
											var o = r.arg;
											C( n );
										}
										return o;
									}
								}
								throw Error( 'illegal catch attempt' );
							},
							delegateYield: function ( e, n, r ) {
								return (
									( this.delegate = {
										iterator: O( e ),
										resultName: n,
										nextLoc: r,
									} ),
									'next' === this.method && ( this.arg = t ),
									g
								);
							},
						} ),
						e
					);
				}
				function h( t, e, n, r, o, a, i ) {
					try {
						var c = t[ a ]( i ),
							s = c.value;
					} catch ( t ) {
						return void n( t );
					}
					c.done ? e( s ) : Promise.resolve( s ).then( r, o );
				}
				function v( t ) {
					return function () {
						var e = this,
							n = arguments;
						return new Promise( function ( r, o ) {
							var a = t.apply( e, n );
							function i( t ) {
								h( a, r, o, i, c, 'next', t );
							}
							function c( t ) {
								h( a, r, o, i, c, 'throw', t );
							}
							i( void 0 );
						} );
					};
				}
				var y = {
					currentPage: 1,
					perPage: 20,
					totalPages: 1,
					totalItems: 0,
					filters: { status: '', category: '', search: '' },
					codeEditor: null,
					init: function () {
						document.getElementById( 'wp-aie-functions' ) &&
							( this.bindEvents(),
							this.loadFunctions(),
							d.init( this ) );
					},
					bindEvents: function () {
						var t,
							e,
							n,
							r,
							o,
							a,
							i,
							c,
							s,
							u,
							l,
							p = this;
						null ===
							( t =
								document.querySelector(
									'.aie-new-function'
								) ) ||
							void 0 === t ||
							t.addEventListener( 'click', function () {
								p.openEditorModal();
							} ),
							null ===
								( e = document.querySelector(
									'.aie-browse-library'
								) ) ||
								void 0 === e ||
								e.addEventListener( 'click', function () {
									d.openLibrary();
								} ),
							null ===
								( n =
									document.getElementById(
										'aie-filter-status'
									) ) ||
								void 0 === n ||
								n.addEventListener( 'change', function ( t ) {
									( p.filters.status = t.target.value ),
										( p.currentPage = 1 ),
										p.loadFunctions();
								} ),
							null ===
								( r = document.getElementById(
									'aie-filter-category'
								) ) ||
								void 0 === r ||
								r.addEventListener( 'change', function ( t ) {
									( p.filters.category = t.target.value ),
										( p.currentPage = 1 ),
										p.loadFunctions();
								} ),
							null ===
								( o =
									document.getElementById(
										'aie-filter-search'
									) ) ||
								void 0 === o ||
								o.addEventListener( 'input', function ( t ) {
									clearTimeout( l ),
										( l = setTimeout( function () {
											( p.filters.search =
												t.target.value ),
												( p.currentPage = 1 ),
												p.loadFunctions();
										}, 500 ) );
								} ),
							null ===
								( a =
									document.querySelector(
										'.aie-filter-clear'
									) ) ||
								void 0 === a ||
								a.addEventListener( 'click', function () {
									p.clearFilters();
								} ),
							null ===
								( i =
									document.querySelector(
										'.aie-prev-page'
									) ) ||
								void 0 === i ||
								i.addEventListener( 'click', function () {
									p.currentPage > 1 &&
										( p.currentPage--, p.loadFunctions() );
								} ),
							null ===
								( c =
									document.querySelector(
										'.aie-next-page'
									) ) ||
								void 0 === c ||
								c.addEventListener( 'click', function () {
									p.currentPage < p.totalPages &&
										( p.currentPage++, p.loadFunctions() );
								} ),
							document
								.querySelectorAll(
									'.aie-modal-close, .aie-modal-cancel'
								)
								.forEach( function ( t ) {
									t.addEventListener(
										'click',
										function ( t ) {
											var e =
												t.target.closest(
													'.aie-modal'
												);
											e && p.closeModal( e );
										}
									);
								} ),
							null ===
								( s =
									document.querySelector(
										'.aie-save-function'
									) ) ||
								void 0 === s ||
								s.addEventListener( 'click', function () {
									p.saveFunction();
								} ),
							null ===
								( u =
									document.querySelector(
										'.aie-test-function'
									) ) ||
								void 0 === u ||
								u.addEventListener( 'click', function () {
									p.testFunction();
								} ),
							document
								.querySelectorAll( '.aie-modal-backdrop' )
								.forEach( function ( t ) {
									t.addEventListener(
										'click',
										function ( t ) {
											var e =
												t.target.closest(
													'.aie-modal'
												);
											e && p.closeModal( e );
										}
									);
								} );
					},
					loadFunctions: function () {
						var t = this;
						return v(
							f().mark( function e() {
								var n, r, o, a, i, c;
								return f().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													if (
														( r =
															document.getElementById(
																'aie-functions-tbody'
															) )
													) {
														e.next = 3;
														break;
													}
													return e.abrupt( 'return' );
												case 3:
													return (
														( r.innerHTML =
															'\n\t\t\t<tr class="aie-loading-row">\n\t\t\t\t<td colspan="7" style="text-align:center;">\n\t\t\t\t\t<span class="spinner is-active"></span>\n\t\t\t\t\t'.concat(
																( null ===
																	( n =
																		window.aieData ) ||
																void 0 === n ||
																null ===
																	( n =
																		n.i18n ) ||
																void 0 === n
																	? void 0
																	: n.loading ) ||
																	'Loading...',
																'\n\t\t\t\t</td>\n\t\t\t</tr>\n\t\t'
															) ),
														( e.prev = 4 ),
														( e.next = 7 ),
														fetch(
															window.aieData
																.ajaxUrl,
															{
																method: 'POST',
																headers: {
																	'Content-Type':
																		'application/x-www-form-urlencoded',
																},
																body: new URLSearchParams(
																	{
																		action: 'aie_functions_get_all',
																		nonce:
																			( null ===
																				( o =
																					window.aieData ) ||
																			void 0 ===
																				o
																				? void 0
																				: o.nonce ) ||
																			'',
																		status: t
																			.filters
																			.status,
																		category:
																			t
																				.filters
																				.category,
																		search: t
																			.filters
																			.search,
																		page: t.currentPage,
																		per_page:
																			t.perPage,
																	}
																),
															}
														)
													);
												case 7:
													return (
														( a = e.sent ),
														( e.next = 10 ),
														a.json()
													);
												case 10:
													if (
														( i = e.sent ).success
													) {
														e.next = 13;
														break;
													}
													throw new Error(
														( null ===
															( c = i.data ) ||
														void 0 === c
															? void 0
															: c.message ) ||
															'Failed to load functions'
													);
												case 13:
													( t.totalPages =
														i.data.total_pages ||
														1 ),
														( t.totalItems =
															i.data.total || 0 ),
														t.renderTable(
															i.data.functions ||
																[]
														),
														t.updatePagination(),
														( e.next = 23 );
													break;
												case 19:
													( e.prev = 19 ),
														( e.t0 = e.catch( 4 ) ),
														console.error(
															'Error loading functions:',
															e.t0
														),
														( r.innerHTML =
															'\n\t\t\t\t<tr>\n\t\t\t\t\t<td colspan="7" style="text-align:center; color:#dc3232;">\n\t\t\t\t\t\t<span class="dashicons dashicons-warning"></span>\n\t\t\t\t\t\t'.concat(
																e.t0.message,
																'\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t'
															) );
												case 23:
												case 'end':
													return e.stop();
											}
									},
									e,
									null,
									[ [ 4, 19 ] ]
								);
							} )
						)();
					},
					renderTable: function ( t ) {
						var e,
							n = this,
							o = document.getElementById(
								'aie-functions-tbody'
							);
						o &&
							( 0 !== t.length
								? ( ( o.innerHTML = t
										.map( function ( t ) {
											return '\n\t\t\t<tr data-function-id="'
												.concat(
													t.id,
													'">\n\t\t\t\t<td class="column-name">\n\t\t\t\t\t<strong>'
												)
												.concat(
													n.escapeHtml( t.name ),
													'</strong>\n\t\t\t\t</td>\n\t\t\t\t<td class="column-description">\n\t\t\t\t\t'
												)
												.concat(
													t.description
														? n.escapeHtml(
																t.description
														  )
														: '<em style="color:#999;">No description</em>',
													'\n\t\t\t\t</td>\n\t\t\t\t<td class="column-category">\n\t\t\t\t\t'
												)
												.concat(
													n.getCategoryLabel(
														t.category
													),
													'\n\t\t\t\t</td>\n\t\t\t\t<td class="column-source">\n\t\t\t\t\t'
												)
												.concat(
													n.getSourceBadge(
														t.source
													),
													'\n\t\t\t\t</td>\n\t\t\t\t<td class="column-status">\n\t\t\t\t\t'
												)
												.concat(
													n.getStatusBadge(
														t.status
													),
													'\n\t\t\t\t</td>\n\t\t\t\t<td class="column-usage">\n\t\t\t\t\t'
												)
												.concat(
													t.usage_count || 0,
													'\n\t\t\t\t</td>\n\t\t\t\t<td class="column-actions">\n\t\t\t\t\t<button type="button" class="button button-small aie-edit-function" data-id="'
												)
												.concat(
													t.id,
													'" title="Edit">\n\t\t\t\t\t\t<span class="dashicons dashicons-edit"></span>\n\t\t\t\t\t</button>\n\t\t\t\t\t<button type="button" class="button button-small aie-delete-function" data-id="'
												)
												.concat(
													t.id,
													'" title="Delete">\n\t\t\t\t\t\t<span class="dashicons dashicons-trash"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</td>\n\t\t\t</tr>\n\t\t'
												);
										} )
										.join( '' ) ),
								  o
										.querySelectorAll(
											'.aie-edit-function'
										)
										.forEach( function ( t ) {
											t.addEventListener(
												'click',
												function ( t ) {
													var e =
														t.currentTarget.dataset
															.id;
													n.openEditorModal( e );
												}
											);
										} ),
								  o
										.querySelectorAll(
											'.aie-delete-function'
										)
										.forEach( function ( t ) {
											t.addEventListener(
												'click',
												( function () {
													var t = v(
														f().mark(
															function t( e ) {
																var o, a;
																return f().wrap(
																	function (
																		t
																	) {
																		for (;;)
																			switch (
																				( t.prev =
																					t.next )
																			) {
																				case 0:
																					return (
																						( a =
																							e
																								.currentTarget
																								.dataset
																								.id ),
																						( t.next = 3 ),
																						r(
																							( null ===
																								( o =
																									window.aieData ) ||
																							void 0 ===
																								o ||
																							null ===
																								( o =
																									o.i18n ) ||
																							void 0 ===
																								o
																								? void 0
																								: o.confirm_delete ) ||
																								'Are you sure you want to delete this function?'
																						)
																					);
																				case 3:
																					t.sent &&
																						n.deleteFunction(
																							a
																						);
																				case 5:
																				case 'end':
																					return t.stop();
																			}
																	},
																	t
																);
															}
														)
													);
													return function ( e ) {
														return t.apply(
															this,
															arguments
														);
													};
												} )()
											);
										} ) )
								: ( o.innerHTML =
										'\n\t\t\t\t<tr>\n\t\t\t\t\t<td colspan="7" style="text-align:center; padding:40px;">\n\t\t\t\t\t\t<div style="display:flex; flex-direction:column; align-items:center; gap:10px;">\n\t\t\t\t\t\t\t<span class="dashicons dashicons-info" style="font-size:48px; opacity:0.3;"></span>\n\t\t\t\t\t\t\t<p style="margin:23px 0 0 0; color:#666;">\n\t\t\t\t\t\t\t\t'.concat(
											( null === ( e = window.aieData ) ||
											void 0 === e ||
											null === ( e = e.i18n ) ||
											void 0 === e
												? void 0
												: e.no_functions ) ||
												'No functions found. Create your first function or browse the library.',
											'\n\t\t\t\t\t\t\t</p>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t'
										) ) );
					},
					updatePagination: function () {
						var t = document.querySelector( '.aie-current-page' ),
							e = document.querySelector( '.aie-total-pages' ),
							n = document.querySelector( '.aie-prev-page' ),
							r = document.querySelector( '.aie-next-page' ),
							o = document.querySelector(
								'.aie-pagination-info'
							);
						if (
							( t && ( t.textContent = this.currentPage ),
							e && ( e.textContent = this.totalPages ),
							n && ( n.disabled = this.currentPage <= 1 ),
							r &&
								( r.disabled =
									this.currentPage >= this.totalPages ),
							o )
						) {
							var a = ( this.currentPage - 1 ) * this.perPage + 1,
								i = Math.min(
									this.currentPage * this.perPage,
									this.totalItems
								);
							o.textContent = 'Showing '
								.concat( a, '-' )
								.concat( i, ' of ' )
								.concat( this.totalItems, ' functions' );
						}
					},
					openEditorModal: function () {
						var t = arguments,
							e = this;
						return v(
							f().mark( function r() {
								var o, a, i, c, s, u, l, d, p, h, v, y;
								return f().wrap(
									function ( r ) {
										for (;;)
											switch ( ( r.prev = r.next ) ) {
												case 0:
													if (
														( ( o =
															t.length > 0 &&
															void 0 !== t[ 0 ]
																? t[ 0 ]
																: null ),
														( a =
															document.getElementById(
																'aie-function-editor-modal'
															) ),
														( i =
															a.querySelector(
																'.aie-modal-title'
															) ),
														( c =
															document.getElementById(
																'aie-function-form'
															) ),
														a && c )
													) {
														r.next = 6;
														break;
													}
													return r.abrupt( 'return' );
												case 6:
													if (
														( c.reset(),
														( document.getElementById(
															'aie-function-id'
														).value = '' ),
														( document.querySelector(
															'.aie-test-results'
														).style.display =
															'none' ),
														! o )
													) {
														r.next = 37;
														break;
													}
													return (
														( i.textContent =
															( null ===
																( s =
																	window.aieData ) ||
															void 0 === s ||
															null ===
																( s =
																	s.i18n ) ||
															void 0 === s
																? void 0
																: s.edit_function ) ||
															'Edit Function' ),
														( r.prev = 11 ),
														( r.next = 14 ),
														fetch(
															window.aieData
																.ajaxUrl,
															{
																method: 'POST',
																headers: {
																	'Content-Type':
																		'application/x-www-form-urlencoded',
																},
																body: new URLSearchParams(
																	{
																		action: 'aie_functions_get',
																		nonce:
																			( null ===
																				( u =
																					window.aieData ) ||
																			void 0 ===
																				u
																				? void 0
																				: u.nonce ) ||
																			'',
																		id: o,
																	}
																),
															}
														)
													);
												case 14:
													return (
														( l = r.sent ),
														( r.next = 17 ),
														l.json()
													);
												case 17:
													if (
														( d = r.sent ).success
													) {
														r.next = 20;
														break;
													}
													throw new Error(
														( null ===
															( p = d.data ) ||
														void 0 === p
															? void 0
															: p.message ) ||
															'Failed to load function'
													);
												case 20:
													( h = d.data ),
														( document.getElementById(
															'aie-function-id'
														).value = h.id ),
														( document.getElementById(
															'aie-function-name'
														).value = h.name ),
														( document.getElementById(
															'aie-function-description'
														).value =
															h.description ||
															'' ),
														( document.getElementById(
															'aie-function-category'
														).value = h.category ),
														( document.getElementById(
															'aie-function-code'
														).value = h.code ),
														( document.getElementById(
															'aie-function-status'
														).value = h.status ),
														e.codeEditor &&
															e.codeEditor.codemirror.setValue(
																h.code
															),
														( r.next = 35 );
													break;
												case 30:
													return (
														( r.prev = 30 ),
														( r.t0 =
															r.catch( 11 ) ),
														console.error(
															'Error loading function:',
															r.t0
														),
														n( r.t0.message, a ),
														r.abrupt( 'return' )
													);
												case 35:
													r.next = 38;
													break;
												case 37:
													i.textContent =
														( null ===
															( v =
																window.aieData ) ||
														void 0 === v ||
														null ===
															( v = v.i18n ) ||
														void 0 === v
															? void 0
															: v.new_function ) ||
														'New Function';
												case 38:
													( a.style.display =
														'flex' ),
														( document.body.style.overflow =
															'hidden' ),
														! e.codeEditor &&
															window.wp &&
															window.wp
																.codeEditor &&
															( y =
																document.getElementById(
																	'aie-function-code'
																) ) &&
															( e.codeEditor =
																window.wp.codeEditor.initialize(
																	y,
																	{
																		codemirror:
																			{
																				mode: 'php',
																				lineNumbers:
																					! 0,
																				lineWrapping:
																					! 0,
																				indentUnit: 4,
																				indentWithTabs:
																					! 0,
																				autoCloseBrackets:
																					! 0,
																				matchBrackets:
																					! 0,
																				styleActiveLine:
																					! 0,
																				continueComments:
																					! 0,
																			},
																	}
																) );
												case 41:
												case 'end':
													return r.stop();
											}
									},
									r,
									null,
									[ [ 11, 30 ] ]
								);
							} )
						)();
					},
					closeModal: function ( t ) {
						( t.style.display = 'none' ),
							( document.body.style.overflow = '' );
					},
					saveFunction: function () {
						var r = this;
						return v(
							f().mark( function o() {
								var a, i, c, s, u, l, d, p, h, v;
								return f().wrap(
									function ( o ) {
										for (;;)
											switch ( ( o.prev = o.next ) ) {
												case 0:
													if (
														( i =
															document.getElementById(
																'aie-function-form'
															) ).checkValidity()
													) {
														o.next = 4;
														break;
													}
													return (
														i.reportValidity(),
														o.abrupt( 'return' )
													);
												case 4:
													return (
														( c =
															document.getElementById(
																'aie-function-code'
															).value ),
														r.codeEditor &&
															r.codeEditor
																.codemirror &&
															( c =
																r.codeEditor.codemirror.getValue() ),
														( s =
															document.getElementById(
																'aie-function-id'
															).value ),
														( u = {
															action: s
																? 'aie_functions_update'
																: 'aie_functions_create',
															nonce:
																( null ===
																	( a =
																		window.aieData ) ||
																void 0 === a
																	? void 0
																	: a.nonce ) ||
																'',
															name: document.getElementById(
																'aie-function-name'
															).value,
															description:
																document.getElementById(
																	'aie-function-description'
																).value,
															category:
																document.getElementById(
																	'aie-function-category'
																).value,
															code: c,
															status: document.getElementById(
																'aie-function-status'
															).value,
														} ),
														s && ( u.id = s ),
														( o.prev = 9 ),
														( o.next = 12 ),
														fetch(
															window.aieData
																.ajaxUrl,
															{
																method: 'POST',
																headers: {
																	'Content-Type':
																		'application/x-www-form-urlencoded',
																},
																body: new URLSearchParams(
																	u
																),
															}
														)
													);
												case 12:
													return (
														( d = o.sent ),
														( o.next = 15 ),
														d.json()
													);
												case 15:
													if (
														( p = o.sent ).success
													) {
														o.next = 18;
														break;
													}
													throw new Error(
														( null ===
															( h = p.data ) ||
														void 0 === h
															? void 0
															: h.message ) ||
															'Failed to save function'
													);
												case 18:
													t(
														( null ===
															( l =
																window.aieData ) ||
														void 0 === l ||
														null ===
															( l = l.i18n ) ||
														void 0 === l
															? void 0
															: l.function_saved ) ||
															'Function saved successfully'
													),
														r.closeModal(
															document.getElementById(
																'aie-function-editor-modal'
															)
														),
														r.loadFunctions(),
														( o.next = 28 );
													break;
												case 23:
													( o.prev = 23 ),
														( o.t0 = o.catch( 9 ) ),
														console.error(
															'Error saving function:',
															o.t0
														),
														( v =
															document.getElementById(
																'aie-function-editor-modal'
															) ) &&
														'flex' ===
															v.style.display
															? n(
																	o.t0
																		.message,
																	v
															  )
															: e( o.t0.message );
												case 28:
												case 'end':
													return o.stop();
											}
									},
									o,
									null,
									[ [ 9, 23 ] ]
								);
							} )
						)();
					},
					deleteFunction: function ( n ) {
						var r = this;
						return v(
							f().mark( function o() {
								var a, i, c, s, u;
								return f().wrap(
									function ( o ) {
										for (;;)
											switch ( ( o.prev = o.next ) ) {
												case 0:
													return (
														( o.prev = 0 ),
														( o.next = 3 ),
														fetch(
															window.aieData
																.ajaxUrl,
															{
																method: 'POST',
																headers: {
																	'Content-Type':
																		'application/x-www-form-urlencoded',
																},
																body: new URLSearchParams(
																	{
																		action: 'aie_functions_delete',
																		nonce:
																			( null ===
																				( a =
																					window.aieData ) ||
																			void 0 ===
																				a
																				? void 0
																				: a.nonce ) ||
																			'',
																		id: n,
																	}
																),
															}
														)
													);
												case 3:
													return (
														( c = o.sent ),
														( o.next = 6 ),
														c.json()
													);
												case 6:
													if (
														( s = o.sent ).success
													) {
														o.next = 9;
														break;
													}
													throw new Error(
														( null ===
															( u = s.data ) ||
														void 0 === u
															? void 0
															: u.message ) ||
															'Failed to delete function'
													);
												case 9:
													t(
														( null ===
															( i =
																window.aieData ) ||
														void 0 === i ||
														null ===
															( i = i.i18n ) ||
														void 0 === i
															? void 0
															: i.function_deleted ) ||
															'Function deleted successfully'
													),
														r.loadFunctions(),
														( o.next = 17 );
													break;
												case 13:
													( o.prev = 13 ),
														( o.t0 = o.catch( 0 ) ),
														console.error(
															'Error deleting function:',
															o.t0
														),
														e( o.t0.message );
												case 17:
												case 'end':
													return o.stop();
											}
									},
									o,
									null,
									[ [ 0, 13 ] ]
								);
							} )
						)();
					},
					testFunction: function () {
						var t = this;
						return v(
							f().mark( function r() {
								var o, a, i, c, s, u, l, d, p;
								return f().wrap(
									function ( r ) {
										for (;;)
											switch ( ( r.prev = r.next ) ) {
												case 0:
													if (
														( ( o =
															document.getElementById(
																'aie-function-code'
															).value ),
														t.codeEditor &&
															t.codeEditor
																.codemirror &&
															( o =
																t.codeEditor.codemirror.getValue() ),
														( a =
															document.getElementById(
																'aie-test-value'
															) ),
														( i = a.value ),
														( c =
															document.querySelector(
																'.aie-test-results'
															) ),
														( s =
															document.getElementById(
																'aie-function-editor-modal'
															) ),
														o )
													) {
														r.next = 9;
														break;
													}
													return (
														s &&
														'flex' ===
															s.style.display
															? n(
																	'Please enter function code first',
																	s
															  )
															: e(
																	'Please enter function code first'
															  ),
														r.abrupt( 'return' )
													);
												case 9:
													if ( i && i.trim() ) {
														r.next = 13;
														break;
													}
													return (
														a.focus(),
														a.select(),
														r.abrupt( 'return' )
													);
												case 13:
													return (
														( r.prev = 13 ),
														( r.next = 16 ),
														fetch(
															window.aieData
																.ajaxUrl,
															{
																method: 'POST',
																headers: {
																	'Content-Type':
																		'application/x-www-form-urlencoded',
																},
																body: new URLSearchParams(
																	{
																		action: 'aie_functions_test',
																		nonce:
																			( null ===
																				( u =
																					window.aieData ) ||
																			void 0 ===
																				u
																				? void 0
																				: u.nonce ) ||
																			'',
																		code: o,
																		value: i,
																	}
																),
															}
														)
													);
												case 16:
													return (
														( l = r.sent ),
														( r.next = 19 ),
														l.json()
													);
												case 19:
													if (
														( d = r.sent ).success
													) {
														r.next = 22;
														break;
													}
													throw new Error(
														( null ===
															( p = d.data ) ||
														void 0 === p
															? void 0
															: p.message ) ||
															'Test failed'
													);
												case 22:
													( document.querySelector(
														'.aie-test-input'
													).textContent =
														void 0 !== d.data.input
															? d.data.input
															: i ),
														( document.querySelector(
															'.aie-test-output'
														).textContent =
															void 0 !==
															d.data.output
																? d.data.output
																: '' ),
														( c.style.display =
															'block' ),
														( r.next = 31 );
													break;
												case 27:
													( r.prev = 27 ),
														( r.t0 =
															r.catch( 13 ) ),
														console.error(
															'Error testing function:',
															r.t0
														),
														s &&
														'flex' ===
															s.style.display
															? n(
																	r.t0
																		.message,
																	s
															  )
															: e( r.t0.message );
												case 31:
												case 'end':
													return r.stop();
											}
									},
									r,
									null,
									[ [ 13, 27 ] ]
								);
							} )
						)();
					},
					clearFilters: function () {
						( this.filters = {
							status: '',
							category: '',
							search: '',
						} ),
							( document.getElementById(
								'aie-filter-status'
							).value = '' ),
							( document.getElementById(
								'aie-filter-category'
							).value = '' ),
							( document.getElementById(
								'aie-filter-search'
							).value = '' ),
							( this.currentPage = 1 ),
							this.loadFunctions();
					},
					getCategoryLabel: function ( t ) {
						return (
							{
								string: 'String Operations',
								date: 'Date & Time',
								numeric: 'Numeric Operations',
								html: 'HTML Operations',
								wordpress: 'WordPress',
								validation: 'Validation',
								advanced: 'Advanced',
								custom: 'Custom',
							}[ t ] || t
						);
					},
					getSourceBadge: function ( t ) {
						return t.startsWith( 'library:' )
							? '<span class="aie-badge aie-badge-library">Library</span>'
							: '<span class="aie-badge aie-badge-custom">Custom</span>';
					},
					getStatusBadge: function ( t ) {
						return 'active' === t
							? '<span class="aie-badge aie-badge-active">Active</span>'
							: '<span class="aie-badge aie-badge-inactive">Inactive</span>';
					},
					escapeHtml: function ( t ) {
						var e = document.createElement( 'div' );
						return ( e.textContent = t ), e.innerHTML;
					},
				};
				const m = y;
				function g( t ) {
					return (
						( g =
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
						g( t )
					);
				}
				function w( t, e ) {
					var n =
						( 'undefined' != typeof Symbol &&
							t[ Symbol.iterator ] ) ||
						t[ '@@iterator' ];
					if ( ! n ) {
						if (
							Array.isArray( t ) ||
							( n = ( function ( t, e ) {
								if ( t ) {
									if ( 'string' == typeof t )
										return b( t, e );
									var n = {}.toString
										.call( t )
										.slice( 8, -1 );
									return (
										'Object' === n &&
											t.constructor &&
											( n = t.constructor.name ),
										'Map' === n || 'Set' === n
											? Array.from( t )
											: 'Arguments' === n ||
											  /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
													n
											  )
											? b( t, e )
											: void 0
									);
								}
							} )( t ) ) ||
							( e && t && 'number' == typeof t.length )
						) {
							n && ( t = n );
							var r = 0,
								o = function () {};
							return {
								s: o,
								n: function () {
									return r >= t.length
										? { done: ! 0 }
										: { done: ! 1, value: t[ r++ ] };
								},
								e: function ( t ) {
									throw t;
								},
								f: o,
							};
						}
						throw new TypeError(
							'Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					}
					var a,
						i = ! 0,
						c = ! 1;
					return {
						s: function () {
							n = n.call( t );
						},
						n: function () {
							var t = n.next();
							return ( i = t.done ), t;
						},
						e: function ( t ) {
							( c = ! 0 ), ( a = t );
						},
						f: function () {
							try {
								i || null == n.return || n.return();
							} finally {
								if ( c ) throw a;
							}
						},
					};
				}
				function b( t, e ) {
					( null == e || e > t.length ) && ( e = t.length );
					for ( var n = 0, r = Array( e ); n < e; n++ )
						r[ n ] = t[ n ];
					return r;
				}
				function x( t, e ) {
					var n = Object.keys( t );
					if ( Object.getOwnPropertySymbols ) {
						var r = Object.getOwnPropertySymbols( t );
						e &&
							( r = r.filter( function ( e ) {
								return Object.getOwnPropertyDescriptor( t, e )
									.enumerable;
							} ) ),
							n.push.apply( n, r );
					}
					return n;
				}
				function E( t, e, n ) {
					return (
						( e = ( function ( t ) {
							var e = ( function ( t, e ) {
								if ( 'object' != g( t ) || ! t ) return t;
								var n = t[ Symbol.toPrimitive ];
								if ( void 0 !== n ) {
									var r = n.call( t, e || 'default' );
									if ( 'object' != g( r ) ) return r;
									throw new TypeError(
										'@@toPrimitive must return a primitive value.'
									);
								}
								return ( 'string' === e ? String : Number )(
									t
								);
							} )( t, 'string' );
							return 'symbol' == g( e ) ? e : e + '';
						} )( e ) ) in t
							? Object.defineProperty( t, e, {
									value: n,
									enumerable: ! 0,
									configurable: ! 0,
									writable: ! 0,
							  } )
							: ( t[ e ] = n ),
						t
					);
				}
				const j = {
					ajax: function ( t ) {
						var e =
								arguments.length > 1 &&
								void 0 !== arguments[ 1 ]
									? arguments[ 1 ]
									: {},
							n =
								arguments.length > 2 &&
								void 0 !== arguments[ 2 ]
									? arguments[ 2 ]
									: 'POST';
						return new Promise( function ( r, o ) {
							var a,
								i,
								c = ( function ( t ) {
									for (
										var e = 1;
										e < arguments.length;
										e++
									) {
										var n =
											null != arguments[ e ]
												? arguments[ e ]
												: {};
										e % 2
											? x( Object( n ), ! 0 ).forEach(
													function ( e ) {
														E( t, e, n[ e ] );
													}
											  )
											: Object.getOwnPropertyDescriptors
											? Object.defineProperties(
													t,
													Object.getOwnPropertyDescriptors(
														n
													)
											  )
											: x( Object( n ) ).forEach(
													function ( e ) {
														Object.defineProperty(
															t,
															e,
															Object.getOwnPropertyDescriptor(
																n,
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
											( null === ( a = window.aieData ) ||
											void 0 === a
												? void 0
												: a.nonce ) || '',
									},
									e
								);
							jQuery
								.ajax( {
									url:
										( null === ( i = window.aieData ) ||
										void 0 === i
											? void 0
											: i.ajaxUrl ) ||
										'/wp-admin/admin-ajax.php',
									type: n,
									data: c,
									dataType: 'json',
								} )
								.done( function ( t ) {
									var e;
									t.success
										? r( t.data || t )
										: o(
												( null === ( e = t.data ) ||
												void 0 === e
													? void 0
													: e.message ) ||
													'Request failed'
										  );
								} )
								.fail( function ( t, e, n ) {
									o(
										'AJAX Error: '
											.concat( e, ' - ' )
											.concat( n )
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
							n = Math.round( t % 60 );
						if ( e < 60 )
							return ''.concat( e, 'm ' ).concat( n, 's' );
						var r = Math.floor( e / 60 ),
							o = e % 60;
						return ''.concat( r, 'h ' ).concat( o, 'm' );
					},
					debounce: function ( t ) {
						var e,
							n =
								arguments.length > 1 &&
								void 0 !== arguments[ 1 ]
									? arguments[ 1 ]
									: 300;
						return function () {
							for (
								var r = arguments.length,
									o = new Array( r ),
									a = 0;
								a < r;
								a++
							)
								o[ a ] = arguments[ a ];
							var i = this;
							clearTimeout( e ),
								( e = setTimeout( function () {
									return t.apply( i, o );
								}, n ) );
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
							n = '\n\t\t\t<div class="'
								.concat( e, '">\n\t\t\t\t<p>' )
								.concat(
									t,
									'</p>\n\t\t\t\t<button type="button" class="notice-dismiss">\n\t\t\t\t\t<span class="screen-reader-text">Dismiss this notice.</span>\n\t\t\t\t</button>\n\t\t\t</div>\n\t\t'
								),
							r = jQuery( n );
						jQuery( '.wrap > h1' ).after( r ),
							setTimeout( function () {
								r.fadeOut( function () {
									return r.remove();
								} );
							}, 5e3 ),
							r.on( 'click', '.notice-dismiss', function () {
								r.fadeOut( function () {
									return r.remove();
								} );
							} );
					},
					validateFile: function ( t ) {
						var e =
								arguments.length > 1 &&
								void 0 !== arguments[ 1 ]
									? arguments[ 1 ]
									: [],
							n =
								arguments.length > 2 &&
								void 0 !== arguments[ 2 ]
									? arguments[ 2 ]
									: 52428800,
							r = [];
						if (
							( t.size > n &&
								r.push(
									'File size ('
										.concat(
											this.formatFileSize( t.size ),
											') exceeds maximum allowed size ('
										)
										.concat( this.formatFileSize( n ), ')' )
								),
							e.length > 0 )
						) {
							var o = t.name.split( '.' ).pop().toLowerCase();
							e.some( function ( e ) {
								return e.startsWith( '.' )
									? e.substring( 1 ) === o
									: t.type === e;
							} ) ||
								r.push(
									'File type .'
										.concat(
											o,
											' is not allowed. Allowed types: '
										)
										.concat( e.join( ', ' ) )
								);
						}
						return { valid: 0 === r.length, errors: r };
					},
					parseCSV: function ( t ) {
						var e,
							n =
								arguments.length > 1 &&
								void 0 !== arguments[ 1 ]
									? arguments[ 1 ]
									: ',',
							r = [],
							o = w( t.split( '\n' ) );
						try {
							for ( o.s(); ! ( e = o.n() ).done;  ) {
								var a = e.value;
								if ( '' !== a.trim() ) {
									for (
										var i = [], c = '', s = ! 1, u = 0;
										u < a.length;
										u++
									) {
										var l = a[ u ];
										'"' === l
											? ( s = ! s )
											: l !== n || s
											? ( c += l )
											: ( i.push( c.trim() ),
											  ( c = '' ) );
									}
									i.push( c.trim() ), r.push( i );
								}
							}
						} catch ( t ) {
							o.e( t );
						} finally {
							o.f();
						}
						return r;
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
						var n = document.createElement( 'a' );
						( n.href = t ),
							( n.download = e || 'export.csv' ),
							document.body.appendChild( n ),
							n.click(),
							document.body.removeChild( n );
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
						var n = e.percentage || 0,
							r = e.processed || 0,
							o = e.total || 0;
						t
							.find( '.aie-progress-bar-fill' )
							.css( 'width', n + '%' ),
							t
								.find( '.aie-progress-percentage' )
								.text( Math.round( n ) + '%' ),
							t.find( '.aie-processed' ).text( r ),
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
						var n = t.message || t.toString();
						this.showNotice( n, 'error' );
					},
				};
				function S( t ) {
					return (
						( S =
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
						S( t )
					);
				}
				function _() {
					_ = function () {
						return e;
					};
					var t,
						e = {},
						n = Object.prototype,
						r = n.hasOwnProperty,
						o =
							Object.defineProperty ||
							function ( t, e, n ) {
								t[ e ] = n.value;
							},
						a = 'function' == typeof Symbol ? Symbol : {},
						i = a.iterator || '@@iterator',
						c = a.asyncIterator || '@@asyncIterator',
						s = a.toStringTag || '@@toStringTag';
					function u( t, e, n ) {
						return (
							Object.defineProperty( t, e, {
								value: n,
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
						u = function ( t, e, n ) {
							return ( t[ e ] = n );
						};
					}
					function l( t, e, n, r ) {
						var a = e && e.prototype instanceof m ? e : m,
							i = Object.create( a.prototype ),
							c = new F( r || [] );
						return o( i, '_invoke', { value: I( t, n, c ) } ), i;
					}
					function d( t, e, n ) {
						try {
							return { type: 'normal', arg: t.call( e, n ) };
						} catch ( t ) {
							return { type: 'throw', arg: t };
						}
					}
					e.wrap = l;
					var p = 'suspendedStart',
						f = 'suspendedYield',
						h = 'executing',
						v = 'completed',
						y = {};
					function m() {}
					function g() {}
					function w() {}
					var b = {};
					u( b, i, function () {
						return this;
					} );
					var x = Object.getPrototypeOf,
						E = x && x( x( O( [] ) ) );
					E && E !== n && r.call( E, i ) && ( b = E );
					var j = ( w.prototype = m.prototype = Object.create( b ) );
					function L( t ) {
						[ 'next', 'throw', 'return' ].forEach( function ( e ) {
							u( t, e, function ( t ) {
								return this._invoke( e, t );
							} );
						} );
					}
					function k( t, e ) {
						function n( o, a, i, c ) {
							var s = d( t[ o ], t, a );
							if ( 'throw' !== s.type ) {
								var u = s.arg,
									l = u.value;
								return l &&
									'object' == S( l ) &&
									r.call( l, '__await' )
									? e.resolve( l.__await ).then(
											function ( t ) {
												n( 'next', t, i, c );
											},
											function ( t ) {
												n( 'throw', t, i, c );
											}
									  )
									: e.resolve( l ).then(
											function ( t ) {
												( u.value = t ), i( u );
											},
											function ( t ) {
												return n( 'throw', t, i, c );
											}
									  );
							}
							c( s.arg );
						}
						var a;
						o( this, '_invoke', {
							value: function ( t, r ) {
								function o() {
									return new e( function ( e, o ) {
										n( t, r, e, o );
									} );
								}
								return ( a = a ? a.then( o, o ) : o() );
							},
						} );
					}
					function I( e, n, r ) {
						var o = p;
						return function ( a, i ) {
							if ( o === h )
								throw Error( 'Generator is already running' );
							if ( o === v ) {
								if ( 'throw' === a ) throw i;
								return { value: t, done: ! 0 };
							}
							for ( r.method = a, r.arg = i; ;  ) {
								var c = r.delegate;
								if ( c ) {
									var s = P( c, r );
									if ( s ) {
										if ( s === y ) continue;
										return s;
									}
								}
								if ( 'next' === r.method )
									r.sent = r._sent = r.arg;
								else if ( 'throw' === r.method ) {
									if ( o === p ) throw ( ( o = v ), r.arg );
									r.dispatchException( r.arg );
								} else
									'return' === r.method &&
										r.abrupt( 'return', r.arg );
								o = h;
								var u = d( e, n, r );
								if ( 'normal' === u.type ) {
									if (
										( ( o = r.done ? v : f ), u.arg === y )
									)
										continue;
									return { value: u.arg, done: r.done };
								}
								'throw' === u.type &&
									( ( o = v ),
									( r.method = 'throw' ),
									( r.arg = u.arg ) );
							}
						};
					}
					function P( e, n ) {
						var r = n.method,
							o = e.iterator[ r ];
						if ( o === t )
							return (
								( n.delegate = null ),
								( 'throw' === r &&
									e.iterator.return &&
									( ( n.method = 'return' ),
									( n.arg = t ),
									P( e, n ),
									'throw' === n.method ) ) ||
									( 'return' !== r &&
										( ( n.method = 'throw' ),
										( n.arg = new TypeError(
											"The iterator does not provide a '" +
												r +
												"' method"
										) ) ) ),
								y
							);
						var a = d( o, e.iterator, n.arg );
						if ( 'throw' === a.type )
							return (
								( n.method = 'throw' ),
								( n.arg = a.arg ),
								( n.delegate = null ),
								y
							);
						var i = a.arg;
						return i
							? i.done
								? ( ( n[ e.resultName ] = i.value ),
								  ( n.next = e.nextLoc ),
								  'return' !== n.method &&
										( ( n.method = 'next' ),
										( n.arg = t ) ),
								  ( n.delegate = null ),
								  y )
								: i
							: ( ( n.method = 'throw' ),
							  ( n.arg = new TypeError(
									'iterator result is not an object'
							  ) ),
							  ( n.delegate = null ),
							  y );
					}
					function Q( t ) {
						var e = { tryLoc: t[ 0 ] };
						1 in t && ( e.catchLoc = t[ 1 ] ),
							2 in t &&
								( ( e.finallyLoc = t[ 2 ] ),
								( e.afterLoc = t[ 3 ] ) ),
							this.tryEntries.push( e );
					}
					function C( t ) {
						var e = t.completion || {};
						( e.type = 'normal' ),
							delete e.arg,
							( t.completion = e );
					}
					function F( t ) {
						( this.tryEntries = [ { tryLoc: 'root' } ] ),
							t.forEach( Q, this ),
							this.reset( ! 0 );
					}
					function O( e ) {
						if ( e || '' === e ) {
							var n = e[ i ];
							if ( n ) return n.call( e );
							if ( 'function' == typeof e.next ) return e;
							if ( ! isNaN( e.length ) ) {
								var o = -1,
									a = function n() {
										for ( ; ++o < e.length;  )
											if ( r.call( e, o ) )
												return (
													( n.value = e[ o ] ),
													( n.done = ! 1 ),
													n
												);
										return (
											( n.value = t ), ( n.done = ! 0 ), n
										);
									};
								return ( a.next = a );
							}
						}
						throw new TypeError( S( e ) + ' is not iterable' );
					}
					return (
						( g.prototype = w ),
						o( j, 'constructor', { value: w, configurable: ! 0 } ),
						o( w, 'constructor', { value: g, configurable: ! 0 } ),
						( g.displayName = u( w, s, 'GeneratorFunction' ) ),
						( e.isGeneratorFunction = function ( t ) {
							var e = 'function' == typeof t && t.constructor;
							return (
								!! e &&
								( e === g ||
									'GeneratorFunction' ===
										( e.displayName || e.name ) )
							);
						} ),
						( e.mark = function ( t ) {
							return (
								Object.setPrototypeOf
									? Object.setPrototypeOf( t, w )
									: ( ( t.__proto__ = w ),
									  u( t, s, 'GeneratorFunction' ) ),
								( t.prototype = Object.create( j ) ),
								t
							);
						} ),
						( e.awrap = function ( t ) {
							return { __await: t };
						} ),
						L( k.prototype ),
						u( k.prototype, c, function () {
							return this;
						} ),
						( e.AsyncIterator = k ),
						( e.async = function ( t, n, r, o, a ) {
							void 0 === a && ( a = Promise );
							var i = new k( l( t, n, r, o ), a );
							return e.isGeneratorFunction( n )
								? i
								: i.next().then( function ( t ) {
										return t.done ? t.value : i.next();
								  } );
						} ),
						L( j ),
						u( j, s, 'Generator' ),
						u( j, i, function () {
							return this;
						} ),
						u( j, 'toString', function () {
							return '[object Generator]';
						} ),
						( e.keys = function ( t ) {
							var e = Object( t ),
								n = [];
							for ( var r in e ) n.push( r );
							return (
								n.reverse(),
								function t() {
									for ( ; n.length;  ) {
										var r = n.pop();
										if ( r in e )
											return (
												( t.value = r ),
												( t.done = ! 1 ),
												t
											);
									}
									return ( t.done = ! 0 ), t;
								}
							);
						} ),
						( e.values = O ),
						( F.prototype = {
							constructor: F,
							reset: function ( e ) {
								if (
									( ( this.prev = 0 ),
									( this.next = 0 ),
									( this.sent = this._sent = t ),
									( this.done = ! 1 ),
									( this.delegate = null ),
									( this.method = 'next' ),
									( this.arg = t ),
									this.tryEntries.forEach( C ),
									! e )
								)
									for ( var n in this )
										't' === n.charAt( 0 ) &&
											r.call( this, n ) &&
											! isNaN( +n.slice( 1 ) ) &&
											( this[ n ] = t );
							},
							stop: function () {
								this.done = ! 0;
								var t = this.tryEntries[ 0 ].completion;
								if ( 'throw' === t.type ) throw t.arg;
								return this.rval;
							},
							dispatchException: function ( e ) {
								if ( this.done ) throw e;
								var n = this;
								function o( r, o ) {
									return (
										( c.type = 'throw' ),
										( c.arg = e ),
										( n.next = r ),
										o &&
											( ( n.method = 'next' ),
											( n.arg = t ) ),
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
										var s = r.call( i, 'catchLoc' ),
											u = r.call( i, 'finallyLoc' );
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
									var n = this.tryEntries.length - 1;
									n >= 0;
									--n
								) {
									var o = this.tryEntries[ n ];
									if (
										o.tryLoc <= this.prev &&
										r.call( o, 'finallyLoc' ) &&
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
										  y )
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
									y
								);
							},
							finish: function ( t ) {
								for (
									var e = this.tryEntries.length - 1;
									e >= 0;
									--e
								) {
									var n = this.tryEntries[ e ];
									if ( n.finallyLoc === t )
										return (
											this.complete(
												n.completion,
												n.afterLoc
											),
											C( n ),
											y
										);
								}
							},
							catch: function ( t ) {
								for (
									var e = this.tryEntries.length - 1;
									e >= 0;
									--e
								) {
									var n = this.tryEntries[ e ];
									if ( n.tryLoc === t ) {
										var r = n.completion;
										if ( 'throw' === r.type ) {
											var o = r.arg;
											C( n );
										}
										return o;
									}
								}
								throw Error( 'illegal catch attempt' );
							},
							delegateYield: function ( e, n, r ) {
								return (
									( this.delegate = {
										iterator: O( e ),
										resultName: n,
										nextLoc: r,
									} ),
									'next' === this.method && ( this.arg = t ),
									y
								);
							},
						} ),
						e
					);
				}
				function L( t, e, n, r, o, a, i ) {
					try {
						var c = t[ a ]( i ),
							s = c.value;
					} catch ( t ) {
						return void n( t );
					}
					c.done ? e( s ) : Promise.resolve( s ).then( r, o );
				}
				function k( t ) {
					return function () {
						var e = this,
							n = arguments;
						return new Promise( function ( r, o ) {
							var a = t.apply( e, n );
							function i( t ) {
								L( a, r, o, i, c, 'next', t );
							}
							function c( t ) {
								L( a, r, o, i, c, 'throw', t );
							}
							i( void 0 );
						} );
					};
				}
				const I = {
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
						var n = jQuery( '#aie-upload-area' );
						n
							.on( 'dragover', function ( t ) {
								t.preventDefault(),
									n.addClass( 'aie-dragover' );
							} )
							.on( 'dragleave', function () {
								n.removeClass( 'aie-dragover' );
							} )
							.on( 'drop', function ( e ) {
								e.preventDefault(),
									n.removeClass( 'aie-dragover' );
								var r = e.originalEvent.dataTransfer.files;
								r.length > 0 && t.handleFile( r[ 0 ] );
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
										j.showNotice(
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
										j.showNotice(
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
						var e = j.validateFile(
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
									j.formatFileSize( t.size )
								);
							var n = this.detectFormat( t.name );
							jQuery( '.aie-file-format' ).text(
								n.toUpperCase()
							),
								'csv' === n &&
									( jQuery( '.aie-format-options' ).show(),
									jQuery( '.aie-csv-options' ).show() ),
								jQuery( '.aie-step-2 .aie-next-step' ).prop(
									'disabled',
									! 1
								),
								this.uploadFile( t );
						} else j.showNotice( e.errors.join( '<br>' ), 'error' );
					},
					uploadFile: function ( t ) {
						var e = this;
						return k(
							_().mark( function n() {
								var r, o, a, i, c;
								return _().wrap(
									function ( n ) {
										for (;;)
											switch ( ( n.prev = n.next ) ) {
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
																( r =
																	window.aieData ) ||
															void 0 === r
																? void 0
																: r.nonce ) ||
																''
														),
														o.append(
															'content_type',
															jQuery(
																'input[name="content_type"]:checked'
															).val()
														),
														( n.prev = 5 ),
														( n.next = 8 ),
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
														! ( i = n.sent ).success
													) {
														n.next = 14;
														break;
													}
													( e.fileData = i.data ),
														j.showNotice(
															'File uploaded successfully',
															'success'
														),
														( n.next = 15 );
													break;
												case 14:
													throw new Error(
														( null ===
															( c = i.data ) ||
														void 0 === c
															? void 0
															: c.message ) ||
															'Upload failed'
													);
												case 15:
													n.next = 21;
													break;
												case 17:
													( n.prev = 17 ),
														( n.t0 = n.catch( 5 ) ),
														j.handleError(
															n.t0,
															'File upload'
														),
														e.removeFile();
												case 21:
												case 'end':
													return n.stop();
											}
									},
									n,
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
						return k(
							_().mark( function e() {
								var n, r, o, a, i;
								return _().wrap( function ( e ) {
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
												( r = t.fileData.preview ),
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
															( n =
																t.fileData
																	.columns ) ||
														void 0 === n
															? void 0
															: n.length ) || 0
													),
													( a = '<tr>' ),
													r.headers &&
														r.headers.forEach(
															function ( t ) {
																a +=
																	'<th>'.concat(
																		j.escapeHtml(
																			t
																		),
																		'</th>'
																	);
															}
														),
													( a += '</tr>' ),
													o.find( 'thead' ).html( a ),
													( i = '' ),
													r.data &&
														r.data.forEach(
															function ( t, e ) {
																( i += '<tr>' ),
																	t.forEach(
																		function (
																			t
																		) {
																			var e =
																				j.escapeHtml(
																					String(
																						t
																					).substring(
																						0,
																						100
																					)
																				);
																			i +=
																				'<td>'.concat(
																					e,
																					'</td>'
																				);
																		}
																	),
																	( i +=
																		'</tr>' );
															}
														),
													o.find( 'tbody' ).html( i );
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
								n = this.getTargetFields( e ),
								r = jQuery( '.aie-mapping-body' ),
								o = '';
							this.fileData.columns.forEach( function ( e, r ) {
								var a,
									i,
									c =
										( null === ( a = t.fileData.preview ) ||
										void 0 === a ||
										null === ( a = a.data ) ||
										void 0 === a ||
										null === ( a = a[ 0 ] ) ||
										void 0 === a
											? void 0
											: a[ r ] ) || '';
								o += '\n\t\t\t\t<tr>\n\t\t\t\t\t<td><strong>'
									.concat(
										j.escapeHtml( e ),
										'</strong></td>\n\t\t\t\t\t<td>\n\t\t\t\t\t\t<select name="field_map['
									)
									.concat(
										r,
										']" class="regular-text">\n\t\t\t\t\t\t\t<option value="">-- '
									)
									.concat(
										( null === ( i = window.aieData ) ||
										void 0 === i ||
										null === ( i = i.i18n ) ||
										void 0 === i
											? void 0
											: i.skip ) || 'Skip',
										' --</option>\n\t\t\t\t\t\t\t'
									)
									.concat(
										n
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
										j.escapeHtml(
											String( c ).substring( 0, 50 )
										),
										'</code></td>\n\t\t\t\t</tr>\n\t\t\t'
									);
							} ),
								r.html( o );
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
								var n = jQuery( this ).val().toLowerCase(),
									r = jQuery( this ).text().toLowerCase();
								if (
									e === n ||
									e === r ||
									e.includes( n ) ||
									n.includes( e )
								)
									return t.val( jQuery( this ).val() ), ! 1;
							} );
						} ),
							j.showNotice( 'Auto-mapping completed', 'success' );
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
										n = jQuery( this ),
										r =
											null ===
												( e = n
													.attr( 'name' )
													.match( /\[(\d+)\]/ ) ) ||
											void 0 === e
												? void 0
												: e[ 1 ],
										o = n.val();
									o && void 0 !== r && ( t[ r ] = o );
								}
							),
							t
						);
					},
					startImport: function () {
						var t = this;
						return k(
							_().mark( function e() {
								var n, r;
								return _().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( e.prev = 0 ),
														( n = {
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
														j.ajax(
															'aie_import_start',
															n
														)
													);
												case 4:
													( r = e.sent ),
														( t.jobId = r.job_id ),
														t.showStep( 6 ),
														t.startProgressTracking(),
														j.showNotice(
															'Import started successfully',
															'success'
														),
														( e.next = 14 );
													break;
												case 11:
													( e.prev = 11 ),
														( e.t0 = e.catch( 0 ) ),
														j.handleError(
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
						return k(
							_().mark( function e() {
								var n;
								return _().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( e.prev = 0 ),
														( e.next = 3 ),
														j.ajax(
															'aie_import_get_progress',
															{ job_id: t.jobId }
														)
													);
												case 3:
													( n = e.sent ),
														j.updateProgressBar(
															jQuery(
																'.aie-step-6'
															),
															n
														),
														'completed' === n.status
															? t.onImportComplete(
																	n
															  )
															: 'failed' ===
																	n.status &&
															  t.onImportFailed(
																	n
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
							j.showNotice(
								'Import completed successfully!',
								'success'
							);
					},
					onImportFailed: function ( t ) {
						clearInterval( this.progressInterval ),
							j.showNotice(
								'Import failed: ' +
									( t.error || 'Unknown error' ),
								'error'
							);
					},
					cancelImport: function () {
						var t = this;
						return k(
							_().mark( function e() {
								return _().wrap(
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
														j.ajax(
															'aie_import_cancel',
															{ job_id: t.jobId }
														)
													);
												case 5:
													clearInterval(
														t.progressInterval
													),
														j.showNotice(
															'Import cancelled',
															'info'
														),
														t.resetWizard(),
														( e.next = 13 );
													break;
												case 10:
													( e.prev = 10 ),
														( e.t0 = e.catch( 2 ) ),
														j.handleError(
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
				function P( t ) {
					return (
						( P =
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
						P( t )
					);
				}
				function Q() {
					Q = function () {
						return e;
					};
					var t,
						e = {},
						n = Object.prototype,
						r = n.hasOwnProperty,
						o =
							Object.defineProperty ||
							function ( t, e, n ) {
								t[ e ] = n.value;
							},
						a = 'function' == typeof Symbol ? Symbol : {},
						i = a.iterator || '@@iterator',
						c = a.asyncIterator || '@@asyncIterator',
						s = a.toStringTag || '@@toStringTag';
					function u( t, e, n ) {
						return (
							Object.defineProperty( t, e, {
								value: n,
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
						u = function ( t, e, n ) {
							return ( t[ e ] = n );
						};
					}
					function l( t, e, n, r ) {
						var a = e && e.prototype instanceof m ? e : m,
							i = Object.create( a.prototype ),
							c = new F( r || [] );
						return o( i, '_invoke', { value: L( t, n, c ) } ), i;
					}
					function d( t, e, n ) {
						try {
							return { type: 'normal', arg: t.call( e, n ) };
						} catch ( t ) {
							return { type: 'throw', arg: t };
						}
					}
					e.wrap = l;
					var p = 'suspendedStart',
						f = 'suspendedYield',
						h = 'executing',
						v = 'completed',
						y = {};
					function m() {}
					function g() {}
					function w() {}
					var b = {};
					u( b, i, function () {
						return this;
					} );
					var x = Object.getPrototypeOf,
						E = x && x( x( O( [] ) ) );
					E && E !== n && r.call( E, i ) && ( b = E );
					var j = ( w.prototype = m.prototype = Object.create( b ) );
					function S( t ) {
						[ 'next', 'throw', 'return' ].forEach( function ( e ) {
							u( t, e, function ( t ) {
								return this._invoke( e, t );
							} );
						} );
					}
					function _( t, e ) {
						function n( o, a, i, c ) {
							var s = d( t[ o ], t, a );
							if ( 'throw' !== s.type ) {
								var u = s.arg,
									l = u.value;
								return l &&
									'object' == P( l ) &&
									r.call( l, '__await' )
									? e.resolve( l.__await ).then(
											function ( t ) {
												n( 'next', t, i, c );
											},
											function ( t ) {
												n( 'throw', t, i, c );
											}
									  )
									: e.resolve( l ).then(
											function ( t ) {
												( u.value = t ), i( u );
											},
											function ( t ) {
												return n( 'throw', t, i, c );
											}
									  );
							}
							c( s.arg );
						}
						var a;
						o( this, '_invoke', {
							value: function ( t, r ) {
								function o() {
									return new e( function ( e, o ) {
										n( t, r, e, o );
									} );
								}
								return ( a = a ? a.then( o, o ) : o() );
							},
						} );
					}
					function L( e, n, r ) {
						var o = p;
						return function ( a, i ) {
							if ( o === h )
								throw Error( 'Generator is already running' );
							if ( o === v ) {
								if ( 'throw' === a ) throw i;
								return { value: t, done: ! 0 };
							}
							for ( r.method = a, r.arg = i; ;  ) {
								var c = r.delegate;
								if ( c ) {
									var s = k( c, r );
									if ( s ) {
										if ( s === y ) continue;
										return s;
									}
								}
								if ( 'next' === r.method )
									r.sent = r._sent = r.arg;
								else if ( 'throw' === r.method ) {
									if ( o === p ) throw ( ( o = v ), r.arg );
									r.dispatchException( r.arg );
								} else
									'return' === r.method &&
										r.abrupt( 'return', r.arg );
								o = h;
								var u = d( e, n, r );
								if ( 'normal' === u.type ) {
									if (
										( ( o = r.done ? v : f ), u.arg === y )
									)
										continue;
									return { value: u.arg, done: r.done };
								}
								'throw' === u.type &&
									( ( o = v ),
									( r.method = 'throw' ),
									( r.arg = u.arg ) );
							}
						};
					}
					function k( e, n ) {
						var r = n.method,
							o = e.iterator[ r ];
						if ( o === t )
							return (
								( n.delegate = null ),
								( 'throw' === r &&
									e.iterator.return &&
									( ( n.method = 'return' ),
									( n.arg = t ),
									k( e, n ),
									'throw' === n.method ) ) ||
									( 'return' !== r &&
										( ( n.method = 'throw' ),
										( n.arg = new TypeError(
											"The iterator does not provide a '" +
												r +
												"' method"
										) ) ) ),
								y
							);
						var a = d( o, e.iterator, n.arg );
						if ( 'throw' === a.type )
							return (
								( n.method = 'throw' ),
								( n.arg = a.arg ),
								( n.delegate = null ),
								y
							);
						var i = a.arg;
						return i
							? i.done
								? ( ( n[ e.resultName ] = i.value ),
								  ( n.next = e.nextLoc ),
								  'return' !== n.method &&
										( ( n.method = 'next' ),
										( n.arg = t ) ),
								  ( n.delegate = null ),
								  y )
								: i
							: ( ( n.method = 'throw' ),
							  ( n.arg = new TypeError(
									'iterator result is not an object'
							  ) ),
							  ( n.delegate = null ),
							  y );
					}
					function I( t ) {
						var e = { tryLoc: t[ 0 ] };
						1 in t && ( e.catchLoc = t[ 1 ] ),
							2 in t &&
								( ( e.finallyLoc = t[ 2 ] ),
								( e.afterLoc = t[ 3 ] ) ),
							this.tryEntries.push( e );
					}
					function C( t ) {
						var e = t.completion || {};
						( e.type = 'normal' ),
							delete e.arg,
							( t.completion = e );
					}
					function F( t ) {
						( this.tryEntries = [ { tryLoc: 'root' } ] ),
							t.forEach( I, this ),
							this.reset( ! 0 );
					}
					function O( e ) {
						if ( e || '' === e ) {
							var n = e[ i ];
							if ( n ) return n.call( e );
							if ( 'function' == typeof e.next ) return e;
							if ( ! isNaN( e.length ) ) {
								var o = -1,
									a = function n() {
										for ( ; ++o < e.length;  )
											if ( r.call( e, o ) )
												return (
													( n.value = e[ o ] ),
													( n.done = ! 1 ),
													n
												);
										return (
											( n.value = t ), ( n.done = ! 0 ), n
										);
									};
								return ( a.next = a );
							}
						}
						throw new TypeError( P( e ) + ' is not iterable' );
					}
					return (
						( g.prototype = w ),
						o( j, 'constructor', { value: w, configurable: ! 0 } ),
						o( w, 'constructor', { value: g, configurable: ! 0 } ),
						( g.displayName = u( w, s, 'GeneratorFunction' ) ),
						( e.isGeneratorFunction = function ( t ) {
							var e = 'function' == typeof t && t.constructor;
							return (
								!! e &&
								( e === g ||
									'GeneratorFunction' ===
										( e.displayName || e.name ) )
							);
						} ),
						( e.mark = function ( t ) {
							return (
								Object.setPrototypeOf
									? Object.setPrototypeOf( t, w )
									: ( ( t.__proto__ = w ),
									  u( t, s, 'GeneratorFunction' ) ),
								( t.prototype = Object.create( j ) ),
								t
							);
						} ),
						( e.awrap = function ( t ) {
							return { __await: t };
						} ),
						S( _.prototype ),
						u( _.prototype, c, function () {
							return this;
						} ),
						( e.AsyncIterator = _ ),
						( e.async = function ( t, n, r, o, a ) {
							void 0 === a && ( a = Promise );
							var i = new _( l( t, n, r, o ), a );
							return e.isGeneratorFunction( n )
								? i
								: i.next().then( function ( t ) {
										return t.done ? t.value : i.next();
								  } );
						} ),
						S( j ),
						u( j, s, 'Generator' ),
						u( j, i, function () {
							return this;
						} ),
						u( j, 'toString', function () {
							return '[object Generator]';
						} ),
						( e.keys = function ( t ) {
							var e = Object( t ),
								n = [];
							for ( var r in e ) n.push( r );
							return (
								n.reverse(),
								function t() {
									for ( ; n.length;  ) {
										var r = n.pop();
										if ( r in e )
											return (
												( t.value = r ),
												( t.done = ! 1 ),
												t
											);
									}
									return ( t.done = ! 0 ), t;
								}
							);
						} ),
						( e.values = O ),
						( F.prototype = {
							constructor: F,
							reset: function ( e ) {
								if (
									( ( this.prev = 0 ),
									( this.next = 0 ),
									( this.sent = this._sent = t ),
									( this.done = ! 1 ),
									( this.delegate = null ),
									( this.method = 'next' ),
									( this.arg = t ),
									this.tryEntries.forEach( C ),
									! e )
								)
									for ( var n in this )
										't' === n.charAt( 0 ) &&
											r.call( this, n ) &&
											! isNaN( +n.slice( 1 ) ) &&
											( this[ n ] = t );
							},
							stop: function () {
								this.done = ! 0;
								var t = this.tryEntries[ 0 ].completion;
								if ( 'throw' === t.type ) throw t.arg;
								return this.rval;
							},
							dispatchException: function ( e ) {
								if ( this.done ) throw e;
								var n = this;
								function o( r, o ) {
									return (
										( c.type = 'throw' ),
										( c.arg = e ),
										( n.next = r ),
										o &&
											( ( n.method = 'next' ),
											( n.arg = t ) ),
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
										var s = r.call( i, 'catchLoc' ),
											u = r.call( i, 'finallyLoc' );
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
									var n = this.tryEntries.length - 1;
									n >= 0;
									--n
								) {
									var o = this.tryEntries[ n ];
									if (
										o.tryLoc <= this.prev &&
										r.call( o, 'finallyLoc' ) &&
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
										  y )
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
									y
								);
							},
							finish: function ( t ) {
								for (
									var e = this.tryEntries.length - 1;
									e >= 0;
									--e
								) {
									var n = this.tryEntries[ e ];
									if ( n.finallyLoc === t )
										return (
											this.complete(
												n.completion,
												n.afterLoc
											),
											C( n ),
											y
										);
								}
							},
							catch: function ( t ) {
								for (
									var e = this.tryEntries.length - 1;
									e >= 0;
									--e
								) {
									var n = this.tryEntries[ e ];
									if ( n.tryLoc === t ) {
										var r = n.completion;
										if ( 'throw' === r.type ) {
											var o = r.arg;
											C( n );
										}
										return o;
									}
								}
								throw Error( 'illegal catch attempt' );
							},
							delegateYield: function ( e, n, r ) {
								return (
									( this.delegate = {
										iterator: O( e ),
										resultName: n,
										nextLoc: r,
									} ),
									'next' === this.method && ( this.arg = t ),
									y
								);
							},
						} ),
						e
					);
				}
				function C( t, e, n, r, o, a, i ) {
					try {
						var c = t[ a ]( i ),
							s = c.value;
					} catch ( t ) {
						return void n( t );
					}
					c.done ? e( s ) : Promise.resolve( s ).then( r, o );
				}
				function F( t ) {
					return function () {
						var e = this,
							n = arguments;
						return new Promise( function ( r, o ) {
							var a = t.apply( e, n );
							function i( t ) {
								C( a, r, o, i, c, 'next', t );
							}
							function c( t ) {
								C( a, r, o, i, c, 'throw', t );
							}
							i( void 0 );
						} );
					};
				}
				const O = {
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
								j.debounce( function () {
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
						return F(
							Q().mark( function e() {
								var n, r, o, a;
								return Q().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( n = t.getFilters() ),
														( r =
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
														j.ajax(
															'aie_export_get_count',
															{
																content_type:
																	jQuery(
																		'input[name="content_type"]:checked'
																	).val(),
																filters: n,
															}
														)
													);
												case 7:
													( a = e.sent ),
														r.text( a.count || 0 ),
														( e.next = 15 );
													break;
												case 11:
													( e.prev = 11 ),
														( e.t0 = e.catch( 4 ) ),
														r.text( '-' ),
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
						return F(
							Q().mark( function e() {
								var n, r, o;
								return Q().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													if (
														0 !==
														( n =
															t.getSelectedFields() )
															.length
													) {
														e.next = 4;
														break;
													}
													return (
														j.showNotice(
															'Please select at least one field to export',
															'error'
														),
														e.abrupt( 'return' )
													);
												case 4:
													return (
														( e.prev = 4 ),
														( r = {
															content_type:
																jQuery(
																	'input[name="content_type"]:checked'
																).val(),
															filters:
																t.getFilters(),
															fields: n,
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
														j.ajax(
															'aie_export_start',
															r
														)
													);
												case 8:
													( o = e.sent ),
														( t.jobId = o.job_id ),
														t.showStep( 5 ),
														t.startProgressTracking(),
														j.showNotice(
															'Export started successfully',
															'success'
														),
														( e.next = 18 );
													break;
												case 15:
													( e.prev = 15 ),
														( e.t0 = e.catch( 4 ) ),
														j.handleError(
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
						return F(
							Q().mark( function e() {
								var n;
								return Q().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( e.prev = 0 ),
														( e.next = 3 ),
														j.ajax(
															'aie_export_get_progress',
															{ job_id: t.jobId }
														)
													);
												case 3:
													( n = e.sent ),
														j.updateProgressBar(
															jQuery(
																'.aie-step-5'
															),
															n
														),
														'completed' === n.status
															? t.onExportComplete(
																	n
															  )
															: 'failed' ===
																	n.status &&
															  t.onExportFailed(
																	n
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
								j.formatFileSize( t.file_size || 0 )
							),
							jQuery( '.aie-result-duration' ).text(
								( null === ( e = t.estimates ) || void 0 === e
									? void 0
									: e.elapsed_formatted ) || '0s'
							),
							jQuery( '.aie-cancel-export' ).hide(),
							jQuery( '.aie-new-export' ).show(),
							j.showNotice(
								'Export completed successfully!',
								'success'
							);
					},
					onExportFailed: function ( t ) {
						clearInterval( this.progressInterval ),
							j.showNotice(
								'Export failed: ' +
									( t.error || 'Unknown error' ),
								'error'
							);
					},
					downloadFile: function () {
						var t = this;
						return F(
							Q().mark( function e() {
								var n;
								return Q().wrap(
									function ( e ) {
										for (;;)
											switch ( ( e.prev = e.next ) ) {
												case 0:
													return (
														( e.prev = 0 ),
														( e.next = 3 ),
														j.ajax(
															'aie_export_download',
															{ job_id: t.jobId }
														)
													);
												case 3:
													( n = e.sent )
														.download_url &&
														j.downloadFile(
															n.download_url,
															n.filename
														),
														( e.next = 10 );
													break;
												case 7:
													( e.prev = 7 ),
														( e.t0 = e.catch( 0 ) ),
														j.handleError(
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
						return F(
							Q().mark( function e() {
								return Q().wrap(
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
														j.ajax(
															'aie_export_cancel',
															{ job_id: t.jobId }
														)
													);
												case 5:
													clearInterval(
														t.progressInterval
													),
														j.showNotice(
															'Export cancelled',
															'info'
														),
														t.resetWizard(),
														( e.next = 13 );
													break;
												case 10:
													( e.prev = 10 ),
														( e.t0 = e.catch( 2 ) ),
														j.handleError(
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
				jQuery( document ).ready( function ( t ) {
					I.init(), O.init(), m.init();
				} );
			},
			205: () => {},
		},
		n = {};
	function r( t ) {
		var o = n[ t ];
		if ( void 0 !== o ) return o.exports;
		var a = ( n[ t ] = { exports: {} } );
		return e[ t ]( a, a.exports, r ), a.exports;
	}
	( r.m = e ),
		( t = [] ),
		( r.O = ( e, n, o, a ) => {
			if ( ! n ) {
				var i = 1 / 0;
				for ( l = 0; l < t.length; l++ ) {
					for (
						var [ n, o, a ] = t[ l ], c = ! 0, s = 0;
						s < n.length;
						s++
					)
						( ! 1 & a || i >= a ) &&
						Object.keys( r.O ).every( ( t ) => r.O[ t ]( n[ s ] ) )
							? n.splice( s--, 1 )
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
			t[ l ] = [ n, o, a ];
		} ),
		( r.o = ( t, e ) => Object.prototype.hasOwnProperty.call( t, e ) ),
		( () => {
			var t = { 847: 0, 252: 0 };
			r.O.j = ( e ) => 0 === t[ e ];
			var e = ( e, n ) => {
					var o,
						a,
						[ i, c, s ] = n,
						u = 0;
					if ( i.some( ( e ) => 0 !== t[ e ] ) ) {
						for ( o in c ) r.o( c, o ) && ( r.m[ o ] = c[ o ] );
						if ( s ) var l = s( r );
					}
					for ( e && e( n ); u < i.length; u++ )
						( a = i[ u ] ),
							r.o( t, a ) && t[ a ] && t[ a ][ 0 ](),
							( t[ a ] = 0 );
					return r.O( l );
				},
				n = ( self.webpackChunkboilerplate =
					self.webpackChunkboilerplate || [] );
			n.forEach( e.bind( null, 0 ) ),
				( n.push = e.bind( null, n.push.bind( n ) ) );
		} )(),
		r.O( void 0, [ 252 ], () => r( 673 ) );
	var o = r.O( void 0, [ 252 ], () => r( 205 ) );
	o = r.O( o );
} )();
//# sourceMappingURL=app.js.map
