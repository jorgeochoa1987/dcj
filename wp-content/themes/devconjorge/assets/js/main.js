/* DevConJorge — interacción del tema. Sin dependencias. */
(function () {
	'use strict';

	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var cfg = window.DCJ || {};

	/* ---------- Menú móvil ---------- */
	var nav = document.querySelector('.nav');
	var toggle = document.querySelector('.nav__toggle');
	if (nav && toggle) {
		toggle.addEventListener('click', function () {
			var open = nav.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		nav.querySelectorAll('.nav__links a').forEach(function (a) {
			a.addEventListener('click', function () {
				nav.classList.remove('is-open');
				toggle.setAttribute('aria-expanded', 'false');
			});
		});
	}

	/* ---------- Aparecer al hacer scroll ---------- */
	var reveals = document.querySelectorAll('.reveal');
	if ('IntersectionObserver' in window && !reduce) {
		var ro = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting) {
					e.target.classList.add('is-in');
					ro.unobserve(e.target);
				}
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
		reveals.forEach(function (el, i) {
			el.style.transitionDelay = (i % 4) * 0.08 + 's';
			ro.observe(el);
		});
	} else {
		reveals.forEach(function (el) { el.classList.add('is-in'); });
	}

	/* ---------- Contadores ---------- */
	var fmt = new Intl.NumberFormat(document.documentElement.lang || 'es');
	function corto(n) {
		if (n >= 1e6) { return (n / 1e6).toFixed(1).replace('.', ',') + 'M'; }
		if (n >= 1e3) { return (n / 1e3).toFixed(1).replace('.', ',') + 'K'; }
		return fmt.format(n);
	}
	function pintar(el, n) {
		el.textContent = el.hasAttribute('data-short') ? corto(n) : fmt.format(n);
	}
	function contar(el) {
		var fin = parseInt(el.getAttribute('data-count'), 10) || 0;
		if (reduce || fin === 0) { pintar(el, fin); return; }
		var dur = 1800 + Math.min(1200, fin / 20);
		var t0 = null;
		function paso(t) {
			if (!t0) { t0 = t; }
			var p = Math.min(1, (t - t0) / dur);
			var e = 1 - Math.pow(1 - p, 4);
			pintar(el, Math.round(fin * e));
			if (p < 1) { requestAnimationFrame(paso); }
		}
		requestAnimationFrame(paso);
	}
	var counts = document.querySelectorAll('[data-count]');
	if ('IntersectionObserver' in window) {
		var co = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting) { contar(e.target); co.unobserve(e.target); }
			});
		}, { threshold: 0.4 });
		counts.forEach(function (el) { pintar(el, 0); co.observe(el); });
	}

	/* ---------- Barra de audio ---------- */
	var aire = document.querySelector('[data-aire]');
	if (aire) {
		var audio = aire.querySelector('[data-aire-audio]');
		var boton = aire.querySelector('[data-aire-play]');
		var seek = aire.querySelector('[data-aire-seek]');
		var ahora = aire.querySelector('[data-aire-ahora]');
		var total = aire.querySelector('[data-aire-total]');
		var tituloEl = aire.querySelector('[data-aire-titulo]');
		var listaBtn = aire.querySelector('[data-aire-lista]');
		var lista = aire.querySelector('[data-aire-caps]');
		var temas = lista ? Array.prototype.slice.call(lista.querySelectorAll('[data-src]')) : [];
		var actual = 0;
		var arrastra = false;
		var oido = 0;
		var ultimoTick = 0;
		var contado = {};
		var finDe = {};
		var oyente = '';
		try { oyente = window.localStorage.getItem('dcj_oyente') || ''; } catch (e) { oyente = ''; }
		if (!/^[a-z0-9]{8,40}$/.test(oyente)) {
			oyente = Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
			try { window.localStorage.setItem('dcj_oyente', oyente); } catch (e) { /* modo privado */ }
		}

		function reloj(seg) {
			if (!isFinite(seg) || seg < 0) { seg = 0; }
			var m = Math.floor(seg / 60);
			var s = Math.floor(seg % 60);
			return m + ':' + (s < 10 ? '0' : '') + s;
		}
		function marcar(valor) {
			seek.value = String(valor);
			seek.style.setProperty('--p', (valor / 10) + '%');
		}
		audio.addEventListener('loadedmetadata', function () {
			total.textContent = reloj(audio.duration);
		});
		function contexto() {
			var ref = '';
			try {
				if (document.referrer) { ref = new URL(document.referrer).host; }
			} catch (e) { ref = ''; }
			if (ref === location.host) { ref = ''; }
			return {
				capitulo: tituloEl ? (tituloEl.textContent || '').trim() : '',
				origen: ref || 'directo',
				pagina: location.pathname || '/',
				dispositivo: window.matchMedia('(max-width: 720px)').matches ? 'movil' : 'escritorio',
				oyente: oyente
			};
		}
		function enviar(tipo, extra) {
			if (!cfg.escucha || !oyente) { return; }
			var cuerpo = contexto();
			if (!cuerpo.capitulo) { return; }
			cuerpo.tipo = tipo;
			if (extra) {
				Object.keys(extra).forEach(function (k) { cuerpo[k] = extra[k]; });
			}
			var json = JSON.stringify(cuerpo);
			if (navigator.sendBeacon) {
				navigator.sendBeacon(cfg.escucha, new Blob([json], { type: 'application/json' }));
				return;
			}
			fetch(cfg.escucha, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: json, keepalive: true });
		}
		function vaciar() {
			var seg = Math.round(oido);
			oido = 0;
			if (seg > 0) { enviar('pulso', { segundos: seg }); }
		}
		audio.addEventListener('timeupdate', function () {
			if (!arrastra && audio.duration) {
				marcar(Math.round((audio.currentTime / audio.duration) * 1000));
				ahora.textContent = reloj(audio.currentTime);
			}
			if (!audio.paused) {
				var delta = audio.currentTime - ultimoTick;
				if (delta > 0 && delta < 4) { oido += delta; }
				ultimoTick = audio.currentTime;
				if (oido >= 15) { vaciar(); }
			}
		});
		audio.addEventListener('play', function () {
			aire.classList.add('is-on');
			boton.setAttribute('aria-label', 'Pausar');
			ultimoTick = audio.currentTime || 0;
			if (!contado[actual]) {
				contado[actual] = true;
				enviar('play');
			}
		});
		function parado() {
			aire.classList.remove('is-on');
			boton.setAttribute('aria-label', 'Reproducir');
		}
		function cargar(i, autoplay) {
			var item = temas[i];
			if (!item) { return; }
			vaciar();
			contado[i] = false;
			finDe[i] = false;
			actual = i;
			audio.pause();
			while (audio.firstChild) { audio.removeChild(audio.firstChild); }
			var source = document.createElement('source');
			source.src = item.getAttribute('data-src');
			source.type = item.getAttribute('data-mime') || 'audio/mpeg';
			audio.appendChild(source);
			audio.load();
			if (tituloEl) { tituloEl.textContent = item.getAttribute('data-titulo') || ''; }
			marcar(0);
			ahora.textContent = '0:00';
			total.textContent = '0:00';
			temas.forEach(function (tema, n) {
				if (n === i) { tema.setAttribute('aria-current', 'true'); }
				else { tema.removeAttribute('aria-current'); }
			});
			if (autoplay) {
				var intento = audio.play();
				if (intento && intento.catch) { intento.catch(function () {}); }
			}
		}
		audio.addEventListener('pause', function () {
			parado();
			vaciar();
		});
		audio.addEventListener('ended', function () {
			parado();
			vaciar();
			if (!finDe[actual]) {
				finDe[actual] = true;
				enviar('fin');
			}
			if (actual + 1 < temas.length) { cargar(actual + 1, true); }
		});
		window.addEventListener('pagehide', vaciar);
		boton.addEventListener('click', function () {
			if (audio.paused) { audio.play(); } else { audio.pause(); }
		});
		temas.forEach(function (item, i) {
			item.addEventListener('click', function () {
				cargar(i, true);
				if (lista) { lista.hidden = true; }
				if (listaBtn) { listaBtn.setAttribute('aria-expanded', 'false'); }
			});
		});
		if (listaBtn && lista) {
			listaBtn.addEventListener('click', function () {
				var abrir = lista.hidden;
				lista.hidden = !abrir;
				listaBtn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
			});
		}
		seek.addEventListener('input', function () {
			arrastra = true;
			marcar(Number(seek.value));
			if (audio.duration) {
				audio.currentTime = (Number(seek.value) / 1000) * audio.duration;
				ahora.textContent = reloj(audio.currentTime);
			}
		});
		seek.addEventListener('change', function () { arrastra = false; });
	}

	/* ---------- Píldoras: el bloque manda el audio al reproductor ---------- */
	document.querySelectorAll('[data-pildora]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var src = btn.getAttribute('data-pildora');
			if (!src || !window.CSS || !CSS.escape) { return; }
			var item = document.querySelector('[data-aire-caps] [data-src="' + CSS.escape(src) + '"]');
			if (item) { item.click(); }
		});
	});

	/* ---------- El tablero de 0HYPE pasa por más palabras ---------- */
	var tablero = document.querySelector('[data-palabras]');
	if (tablero && !reduce) {
		var palabras = tablero.getAttribute('data-palabras').split(',');
		var celdas = tablero.querySelectorAll('.flap');
		var indice = 0;
		setInterval(function () {
			indice = (indice + 1) % palabras.length;
			var letras = palabras[indice].toUpperCase();
			tablero.setAttribute('aria-label', palabras.join(', '));
			celdas.forEach(function (celda, i) {
				celda.textContent = letras.charAt(i) || '';
			});
		}, 2600);
	}

	/* ---------- WhatsApp se pega al lado al pasar la comunidad ---------- */
	var pegado = document.querySelector('[data-wa-pegado]');
	var comunidad = document.getElementById('comunidad');
	var pie = document.querySelector('.footer');
	if (pegado && comunidad && 'IntersectionObserver' in window) {
		var pasado = false;
		var enPie = false;
		function pintarWa() {
			pegado.classList.toggle('is-on', pasado && !enPie);
		}
		new IntersectionObserver(function (entries) {
			var e = entries[0];
			pasado = !e.isIntersecting && e.boundingClientRect.top < 0;
			pintarWa();
		}, { threshold: 0 }).observe(comunidad);
		if (pie) {
			new IntersectionObserver(function (entries) {
				enPie = entries[0].isIntersecting;
				pintarWa();
			}, { threshold: 0 }).observe(pie);
		}
	}

	/* ---------- Míralo en 60 s: la app ---------- */
	var app = document.querySelector('[data-reels]');
	if (!app) { return; }

	var feed = app.querySelector('.reels__feed');
	var reels = Array.prototype.slice.call(app.querySelectorAll('.reel[data-id]'));
	var barras = app.querySelectorAll('.reels__progress span');
	var muteBtn = app.querySelector('[data-mute]');
	var muteLabel = app.querySelector('[data-mute-label]');
	var toast = app.querySelector('[data-toast]');
	var prev = app.querySelector('[data-prev]');
	var next = app.querySelector('[data-next]');
	var fila = document.querySelectorAll('[data-ir-reel]');
	var muted = true;
	var visible = false;
	var actual = -1;
	var origen = 'https://www.youtube-nocookie.com';

	if (!reels.length) { return; }

	function aviso(txt) {
		if (!toast) { return; }
		toast.textContent = txt;
		toast.classList.add('is-on');
		clearTimeout(aviso.t);
		aviso.t = setTimeout(function () { toast.classList.remove('is-on'); }, 1800);
	}

	function comando(reel, func, args) {
		var f = reel && reel.querySelector('iframe');
		if (!f || !f.contentWindow) { return; }
		f.contentWindow.postMessage(JSON.stringify({ event: 'command', func: func, args: args || [] }), origen);
	}

	function montar(reel) {
		var cont = reel.querySelector('.reel__player');
		if (cont.firstChild) { return; }
		var id = reel.getAttribute('data-id');
		var src = origen + '/embed/' + encodeURIComponent(id) +
			'?autoplay=1&mute=' + (muted ? 1 : 0) +
			'&playsinline=1&controls=0&rel=0&modestbranding=1&iv_load_policy=3&loop=1&playlist=' + encodeURIComponent(id) +
			'&enablejsapi=1&origin=' + encodeURIComponent(location.origin);
		var f = document.createElement('iframe');
		f.src = src;
		f.title = reel.getAttribute('aria-label') || 'Video';
		f.allow = 'autoplay; encrypted-media; picture-in-picture; web-share';
		f.setAttribute('tabindex', '-1');
		f.addEventListener('load', function () {
			// Pedir a YouTube que reporte tiempo y estado (para la barra de progreso).
			f.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: id, channel: 'widget' }), origen);
		});
		cont.appendChild(f);
	}

	function desmontar(reel) {
		var cont = reel.querySelector('.reel__player');
		if (cont) { cont.innerHTML = ''; }
		reel.classList.remove('is-playing', 'is-paused', 'is-active');
	}

	function activar(i) {
		if (i === actual || i < 0 || i >= reels.length) { return; }
		if (actual > -1) { desmontar(reels[actual]); }
		actual = i;
		var r = reels[i];
		r.classList.add('is-active');
		app.classList.remove('is-paused');
		if (visible) { montar(r); }

		barras.forEach(function (b, k) {
			b.classList.toggle('is-on', k === i);
			b.classList.toggle('is-done', k < i);
			b.style.setProperty('--p', 0);
		});
		fila.forEach(function (b) {
			b.classList.toggle('is-on', parseInt(b.getAttribute('data-ir-reel'), 10) === i);
		});
		if (prev) { prev.disabled = i === 0; }
		if (next) { next.disabled = i === reels.length - 1; }

		// Precarga la miniatura del siguiente.
		var sig = reels[i + 1];
		if (sig) { var img = sig.querySelector('.reel__poster'); if (img) { img.loading = 'eager'; } }
	}

	function ir(i) {
		if (i < 0 || i >= reels.length) { return; }
		feed.scrollTo({ top: reels[i].offsetTop, behavior: reduce ? 'auto' : 'smooth' });
	}

	// Qué reel está en pantalla.
	var io = new IntersectionObserver(function (entries) {
		entries.forEach(function (e) {
			if (e.isIntersecting && e.intersectionRatio > 0.6) {
				activar(parseInt(e.target.getAttribute('data-index'), 10));
			}
		});
	}, { root: feed, threshold: [0.6] });
	reels.forEach(function (r) { io.observe(r); });

	// Solo reproduce cuando la sección está en pantalla.
	var vo = new IntersectionObserver(function (entries) {
		visible = entries[0].isIntersecting;
		if (actual < 0) { return; }
		if (visible) { montar(reels[actual]); } else { desmontar(reels[actual]); reels[actual].classList.add('is-active'); }
	}, { threshold: 0.35 });
	vo.observe(app);

	activar(0);

	// Mensajes de YouTube: estado y progreso.
	window.addEventListener('message', function (ev) {
		if (ev.origin !== origen || typeof ev.data !== 'string') { return; }
		var d;
		try { d = JSON.parse(ev.data); } catch (err) { return; }
		if (!d || d.event !== 'infoDelivery' || !d.info || actual < 0) { return; }
		var f = reels[actual].querySelector('iframe');
		if (!f || ev.source !== f.contentWindow) { return; }
		var info = d.info;
		if (typeof info.playerState === 'number') {
			reels[actual].classList.toggle('is-playing', info.playerState === 1 || info.playerState === 3);
		}
		if (info.currentTime && info.duration && barras[actual]) {
			barras[actual].style.setProperty('--p', Math.min(1, info.currentTime / info.duration).toFixed(4));
		}
	});

	// Toque: una vez pausa, dos veces corazón.
	reels.forEach(function (r) {
		var tap = r.querySelector('[data-tap]');
		var heart = r.querySelector('.reel__heart');
		var like = r.querySelector('[data-like]');
		var timer = null;

		function corazon(x, y) {
			heart.style.setProperty('--x', x + 'px');
			heart.style.setProperty('--y', y + 'px');
			heart.classList.remove('is-on');
			void heart.offsetWidth;
			heart.classList.add('is-on');
			if (like) { like.setAttribute('aria-pressed', 'true'); }
		}

		tap.addEventListener('click', function (ev) {
			var rect = r.getBoundingClientRect();
			var x = ev.clientX - rect.left;
			var y = ev.clientY - rect.top;
			if (timer) {
				clearTimeout(timer);
				timer = null;
				corazon(x, y);
				return;
			}
			timer = setTimeout(function () {
				timer = null;
				if (!r.querySelector('iframe')) { montar(r); return; }
				var pausado = r.classList.toggle('is-paused');
				app.classList.toggle('is-paused', pausado);
				comando(r, pausado ? 'pauseVideo' : 'playVideo');
			}, 260);
		});

		if (like) {
			like.addEventListener('click', function () {
				var on = like.getAttribute('aria-pressed') !== 'true';
				like.setAttribute('aria-pressed', on ? 'true' : 'false');
				if (on) { var rect = r.getBoundingClientRect(); corazon(rect.width / 2, rect.height / 2); }
			});
		}
	});

	// Sonido.
	if (muteBtn) {
		muteBtn.classList.add('is-hint');
		muteBtn.addEventListener('click', function () {
			muted = !muted;
			muteBtn.classList.remove('is-hint');
			muteBtn.setAttribute('aria-pressed', muted ? 'true' : 'false');
			if (muteLabel) { muteLabel.textContent = muted ? 'Activar sonido' : 'Silenciar'; }
			var r = reels[actual];
			comando(r, muted ? 'mute' : 'unMute');
			if (!muted) { comando(r, 'setVolume', [100]); comando(r, 'playVideo'); }
		});
	}

	// Compartir.
	app.querySelectorAll('[data-share]').forEach(function (b) {
		b.addEventListener('click', function () {
			var url = b.getAttribute('data-url');
			var title = b.getAttribute('data-title');
			if (navigator.share) {
				navigator.share({ title: title, text: title + ' — ' + (cfg.handle || ''), url: url }).catch(function () {});
			} else if (navigator.clipboard) {
				navigator.clipboard.writeText(url).then(function () { aviso(cfg.copied || 'Enlace copiado'); });
			}
		});
	});

	// Flechas, teclado y fila.
	if (prev) { prev.addEventListener('click', function () { ir(actual - 1); }); }
	if (next) { next.addEventListener('click', function () { ir(actual + 1); }); }
	feed.addEventListener('keydown', function (ev) {
		if (ev.key === 'ArrowDown' || ev.key === 'j' || ev.key === 'PageDown') { ev.preventDefault(); ir(actual + 1); }
		if (ev.key === 'ArrowUp' || ev.key === 'k' || ev.key === 'PageUp') { ev.preventDefault(); ir(actual - 1); }
		if (ev.key === ' ' || ev.key === 'Enter') {
			ev.preventDefault();
			reels[actual].querySelector('[data-tap]').click();
		}
		if (ev.key === 'm') { if (muteBtn) { muteBtn.click(); } }
	});
	fila.forEach(function (b) {
		b.addEventListener('click', function () {
			var i = parseInt(b.getAttribute('data-ir-reel'), 10);
			ir(i);
			if (window.innerWidth < 900) { app.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' }); }
		});
	});

	// El teléfono del hero abre la app en el primer video.
	document.querySelectorAll('[data-abrir-reel]').forEach(function (a) {
		a.addEventListener('click', function (ev) {
			ev.preventDefault();
			document.getElementById('videos').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
			ir(parseInt(a.getAttribute('data-abrir-reel'), 10) || 0);
			setTimeout(function () { feed.focus({ preventScroll: true }); }, 700);
		});
	});

	// Al pasar el mouse por el feed en escritorio, la rueda cambia de video de uno en uno.
	var bloqueo = false;
	feed.addEventListener('wheel', function (ev) {
		if (Math.abs(ev.deltaY) < 12) { return; }
		var dir = ev.deltaY > 0 ? 1 : -1;
		// En el primer o último video, deja que la página siga bajando o subiendo.
		if ((dir < 0 && actual <= 0) || (dir > 0 && actual >= reels.length - 1)) { return; }
		ev.preventDefault();
		if (bloqueo) { return; }
		bloqueo = true;
		ir(actual + dir);
		setTimeout(function () { bloqueo = false; }, 650);
	}, { passive: false });
})();
