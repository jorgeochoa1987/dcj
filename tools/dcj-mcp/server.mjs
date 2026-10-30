import { AsyncLocalStorage } from "node:async_hooks";
import { readFileSync, existsSync } from "node:fs";
import { basename, dirname, extname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { createMcpExpressApp } from "@modelcontextprotocol/sdk/server/express.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { z } from "zod";

const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const MIME = {
	".jpg": "image/jpeg",
	".jpeg": "image/jpeg",
	".png": "image/png",
	".gif": "image/gif",
	".webp": "image/webp",
};
const TOPE = 12 * 1024 * 1024;

cargarEnv(resolve(RAIZ, ".env"));

const camposExtra = {
	etiquetas: z.array(z.string()).optional().describe("Etiquetas de la entrada"),
	seo_titulo: z.string().optional().describe("Título SEO de Yoast"),
	seo_descripcion: z.string().optional().describe("Meta descripción de Yoast"),
	frase_clave: z.string().optional().describe("Frase clave de Yoast"),
	video_id: z.string().optional().describe("ID del Short. Su miniatura de YouTube queda como imagen destacada"),
};

const EN_HTTP = process.env.MCP_HTTP === "1";
const sesion = new AsyncLocalStorage();

function crearServidor() {
	const server = new McpServer({
		name: "devconjorge",
		version: "1.0.0",
	});

server.registerTool(
	"listar_entradas",
	{
		title: "Listar entradas",
		description: "Lista entradas de la bitácora de devconjorge.com, incluidas las que están en borrador.",
		inputSchema: {
			buscar: z.string().optional().describe("Texto del título o del contenido"),
			estado: z.enum(["publish", "draft", "pending", "any"]).optional().describe("Por defecto: any"),
			cantidad: z.number().int().min(1).max(20).optional().describe("Cuántas devolver. Por defecto 10"),
		},
	},
	async ({ buscar, estado, cantidad }) => {
		try {
			const params = new URLSearchParams({
				per_page: String(cantidad || 10),
				context: "edit",
				status: estado || "any",
				_fields: "id,date,status,slug,link,title,excerpt,featured_media",
			});
			if (buscar) {
				params.set("search", buscar);
			}
			const entradas = await wp("GET", `/wp-json/wp/v2/posts?${params}`);
			const lista = entradas.map((e) => ({
				id: e.id,
				estado: e.status,
				fecha: e.date,
				slug: e.slug,
				titulo: e.title?.raw || e.title?.rendered || "",
				enlace: e.link,
				imagen_destacada: e.featured_media || null,
			}));
			return texto(JSON.stringify(lista, null, 2));
		} catch (error) {
			return fallo(error);
		}
	}
);

server.registerTool(
	"ver_entrada",
	{
		title: "Ver entrada",
		description: "Devuelve el título, el estado, el extracto y el HTML de una entrada.",
		inputSchema: {
			id: z.number().int().positive().describe("ID de la entrada"),
		},
	},
	async ({ id }) => {
		try {
			const e = await wp("GET", `/wp-json/wp/v2/posts/${id}?context=edit`);
			return texto(JSON.stringify({
				id: e.id,
				estado: e.status,
				fecha: e.date,
				slug: e.slug,
				titulo: e.title?.raw || "",
				extracto: e.excerpt?.raw || "",
				enlace: e.link,
				imagen_destacada: e.featured_media || null,
				contenido: e.content?.raw || "",
			}, null, 2));
		} catch (error) {
			return fallo(error);
		}
	}
);

server.registerTool(
	"crear_entrada",
	{
		title: "Crear entrada",
		description: "Crea una entrada en la bitácora. Queda en borrador salvo que el estado sea publish. Acepta etiquetas, SEO de Yoast y el ID del Short para poner su miniatura como imagen destacada.",
		inputSchema: {
			titulo: z.string().min(1).describe("Título de la entrada"),
			contenido: z.string().min(1).describe("Texto o HTML. El texto plano se convierte en párrafos"),
			extracto: z.string().optional(),
			estado: z.enum(["draft", "publish", "pending"]).optional().describe("Por defecto draft"),
			slug: z.string().optional(),
			...camposExtra,
		},
	},
	async ({ titulo, contenido, extracto, estado, slug, ...extra }) => {
		try {
			const cuerpo = {
				title: titulo,
				content: aHtml(contenido),
				status: estado || "draft",
			};
			if (extracto) {
				cuerpo.excerpt = extracto;
			}
			if (slug) {
				cuerpo.slug = slug;
			}
			const e = await wp("POST", "/wp-json/wp/v2/posts", cuerpo);
			const aviso = await completarEntrada(e.id, extra);
			const salida = JSON.parse(resumen(e, "Entrada creada."));
			if (aviso) {
				salida.miniatura = aviso;
			}
			return texto(JSON.stringify(salida, null, 2));
		} catch (error) {
			return fallo(error);
		}
	}
);

server.registerTool(
	"actualizar_entrada",
	{
		title: "Actualizar entrada",
		description: "Actualiza título, contenido, extracto, slug, estado, etiquetas, SEO de Yoast o la miniatura del Short.",
		inputSchema: {
			id: z.number().int().positive(),
			titulo: z.string().optional(),
			contenido: z.string().optional(),
			extracto: z.string().optional(),
			estado: z.enum(["draft", "publish", "pending"]).optional(),
			slug: z.string().optional(),
			...camposExtra,
		},
	},
	async ({ id, titulo, contenido, extracto, estado, slug, ...extra }) => {
		try {
			const cuerpo = {};
			if (titulo) {
				cuerpo.title = titulo;
			}
			if (contenido) {
				cuerpo.content = aHtml(contenido);
			}
			if (extracto) {
				cuerpo.excerpt = extracto;
			}
			if (estado) {
				cuerpo.status = estado;
			}
			if (slug) {
				cuerpo.slug = slug;
			}
			const hayExtra = Object.values(extra).some((valor) => valor != null && valor !== "");
			if (!Object.keys(cuerpo).length && !hayExtra) {
				throw new Error("No hay nada que actualizar.");
			}
			const e = Object.keys(cuerpo).length
				? await wp("POST", `/wp-json/wp/v2/posts/${id}`, cuerpo)
				: await wp("GET", `/wp-json/wp/v2/posts/${id}?context=edit&_fields=id,status,link`);
			const aviso = await completarEntrada(id, extra);
			const salida = JSON.parse(resumen(e, "Entrada actualizada."));
			if (aviso) {
				salida.miniatura = aviso;
			}
			return texto(JSON.stringify(salida, null, 2));
		} catch (error) {
			return fallo(error);
		}
	}
);

server.registerTool(
	"subir_imagen",
	{
		title: "Subir imagen",
		description: "Sube una imagen a Medios de WordPress. En el servidor remoto solo acepta una URL https. Puede dejarla como imagen destacada o insertarla al final del texto.",
		inputSchema: {
			ruta: z.string().optional().describe("Solo en local: ruta del archivo"),
			url: z.string().url().optional().describe("URL https de la imagen"),
			alt: z.string().optional().describe("Texto alternativo"),
			titulo: z.string().optional(),
			entrada_id: z.number().int().positive().optional().describe("Entrada a la que se asocia"),
			destacada: z.boolean().optional().describe("Si es true y hay entrada_id, queda como imagen destacada"),
			en_el_texto: z.boolean().optional().describe("Si es true y hay entrada_id, agrega la imagen al final del contenido"),
			retrato: z.boolean().optional().describe("Si es true, queda como la foto de Jorge en el home"),
			en_el_home: z.boolean().optional().describe("Si es true, se agrega a las fotos del home"),
		},
	},
	async ({ ruta, url, alt, titulo, entrada_id, destacada, en_el_texto, retrato, en_el_home }) => {
		try {
			if (EN_HTTP && ruta) {
				throw new Error("En el servidor solo se acepta una URL https.");
			}
			if (!ruta && !url) {
				throw new Error("Indica una URL https.");
			}
			const archivo = ruta ? await leerLocal(ruta) : await leerUrl(url);
			const media = await wp("POST", "/wp-json/wp/v2/media", archivo.bytes, {
				"Content-Type": archivo.mime,
				"Content-Disposition": `attachment; filename="${archivo.nombre}"`,
			});
			if (alt || titulo) {
				await wp("POST", `/wp-json/wp/v2/media/${media.id}`, {
					...(alt ? { alt_text: alt } : {}),
					...(titulo ? { title: titulo } : {}),
				});
			}
			const resultado = {
				mensaje: "Imagen subida.",
				id: media.id,
				url: media.source_url,
				alt: alt || "",
			};
			if (entrada_id && destacada) {
				await wp("POST", `/wp-json/wp/v2/posts/${entrada_id}`, { featured_media: media.id });
				resultado.imagen_destacada_de = entrada_id;
			}
			if (entrada_id && en_el_texto) {
				const entrada = await wp("GET", `/wp-json/wp/v2/posts/${entrada_id}?context=edit&_fields=content`);
				const previo = entrada.content?.raw || "";
				const figura = `<figure class="wp-block-image"><img src="${media.source_url}" alt="${escapar(alt || titulo || "")}"/></figure>`;
				await wp("POST", `/wp-json/wp/v2/posts/${entrada_id}`, { content: `${previo}\n${figura}` });
				resultado.insertada_en = entrada_id;
			}
			if (retrato) {
				await wp("POST", "/wp-json/dcj/v1/home", { foto_id: media.id });
				resultado.retrato_del_home = media.id;
			}
			if (en_el_home) {
				await wp("POST", "/wp-json/dcj/v1/home", { agregar_foto: media.id });
				resultado.foto_del_home = media.id;
			}
			return texto(JSON.stringify(resultado, null, 2));
		} catch (error) {
			return fallo(error);
		}
	}
	);

	server.registerTool(
		"ver_home",
		{
			title: "Ver home",
			description: "Devuelve las cifras de redes, los videos y las fotos que se ven en el inicio de devconjorge.com.",
			inputSchema: {},
		},
		async () => {
			try {
				const home = await wp("GET", "/wp-json/dcj/v1/home");
				return texto(JSON.stringify(home, null, 2));
			} catch (error) {
				return fallo(error);
			}
		}
	);

	server.registerTool(
		"actualizar_home",
		{
			title: "Actualizar home",
			description: "Cambia las cifras de seguidores, los videos del inicio o la lista de fotos. Para volver a los Shorts automáticos de YouTube, refrescar_videos en true.",
			inputSchema: {
				cifras: z.object({
					youtube: z.number().int().min(0).optional(),
					tiktok: z.number().int().min(0).optional(),
					instagram: z.number().int().min(0).optional(),
					facebook: z.number().int().min(0).optional(),
					whatsapp: z.number().int().min(0).optional(),
					twitch: z.number().int().min(0).optional(),
				}).optional(),
				videos: z.array(z.object({
					id: z.string().describe("ID del Short de YouTube"),
					titulo: z.string().optional(),
					vistas: z.number().int().min(0).optional(),
				})).optional().describe("Si se envía, estos videos reemplazan a los automáticos del canal"),
				refrescar_videos: z.boolean().optional().describe("Quita la lista fija y vuelve a leer el canal"),
				foto_id: z.number().int().positive().optional().describe("ID de Medios para el retrato"),
				fotos: z.array(z.number().int().positive()).optional().describe("IDs de Medios que quedan como fotos del home"),
			},
		},
		async ({ cifras, videos, refrescar_videos, foto_id, fotos }) => {
			try {
				const cuerpo = {};
				if (cifras) {
					cuerpo.cifras = cifras;
				}
				if (videos) {
					cuerpo.videos = videos;
				}
				if (refrescar_videos) {
					cuerpo.refrescar_videos = true;
				}
				if (foto_id) {
					cuerpo.foto_id = foto_id;
				}
				if (fotos) {
					cuerpo.fotos = fotos;
				}
				if (!Object.keys(cuerpo).length) {
					throw new Error("No hay nada que actualizar en el home.");
				}
				const home = await wp("POST", "/wp-json/dcj/v1/home", cuerpo);
				return texto(JSON.stringify(home, null, 2));
			} catch (error) {
				return fallo(error);
			}
		}
	);

	return server;
}

async function main() {
	if (!EN_HTTP) {
		const transport = new StdioServerTransport();
		await crearServidor().connect(transport);
		return;
	}

	if (!(process.env.WORDPRESS_USER || "").trim()) {
		console.error("Falta WORDPRESS_USER.");
		process.exit(1);
	}

	const puerto = Number(process.env.PORT || 3000);
	const app = createMcpExpressApp({ host: "0.0.0.0" });
	app.get("/health", (_req, res) => {
		res.status(200).json({ ok: true });
	});
	app.all("/mcp", async (req, res) => {
		if (req.method === "GET" || req.method === "DELETE") {
			res.status(405).json({ error: "method_not_allowed" });
			return;
		}
		const header = req.get("authorization") || "";
		const token = header.startsWith("Bearer ") ? header.slice("Bearer ".length).trim() : "";
		const acceso = await tokenDeWordpress(token);
		if (!acceso) {
			res.status(401).json({ error: "unauthorized" });
			return;
		}
		const mcp = crearServidor();
		const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined });
		try {
			await sesion.run(acceso, async () => {
				await mcp.connect(transport);
				await transport.handleRequest(req, res, req.body);
			});
		} catch (error) {
			console.error(error instanceof Error ? error.message : error);
			if (!res.headersSent) {
				res.status(500).json({ error: "error" });
			}
		} finally {
			res.on("close", () => {
				transport.close();
			});
		}
	});
	app.listen(puerto, "0.0.0.0");
}

async function tokenDeWordpress(token) {
	const acceso = leerBearer(token);
	if (!acceso.user || acceso.password.length < 8) {
		return null;
	}
	const url = (process.env.WORDPRESS_URL || "https://devconjorge.com").replace(/\/$/, "");
	try {
		const respuesta = await fetch(`${url}/wp-json/wp/v2/users/me?context=edit`, {
			headers: {
				Authorization: `Basic ${Buffer.from(`${acceso.user}:${acceso.password}`).toString("base64")}`,
				Accept: "application/json",
			},
		});
		return respuesta.ok ? acceso : null;
	} catch {
		return null;
	}
}

function leerBearer(token) {
	const limpio = (token || "").trim();
	const corte = limpio.indexOf(":");
	if (corte > 0) {
		return {
			user: limpio.slice(0, corte).trim(),
			password: limpio.slice(corte + 1).replace(/\s+/g, ""),
		};
	}
	return {
		user: (process.env.WORDPRESS_USER || "").trim(),
		password: limpio.replace(/\s+/g, ""),
	};
}

await main();

function cargarEnv(ruta) {
	if (!existsSync(ruta)) {
		return;
	}
	const lineas = readFileSync(ruta, "utf8").split("\n");
	for (const linea of lineas) {
		const limpia = linea.trim();
		if (!limpia || limpia.startsWith("#") || !limpia.includes("=")) {
			continue;
		}
		const corte = limpia.indexOf("=");
		const clave = limpia.slice(0, corte).trim();
		let valor = limpia.slice(corte + 1).trim();
		if ((valor.startsWith('"') && valor.endsWith('"')) || (valor.startsWith("'") && valor.endsWith("'"))) {
			valor = valor.slice(1, -1);
		}
		if (process.env[clave] == null || process.env[clave] === "") {
			process.env[clave] = valor;
		}
	}
}

function credenciales() {
	const url = (process.env.WORDPRESS_URL || "https://devconjorge.com").replace(/\/$/, "");
	const guardado = sesion.getStore();
	const user = (guardado?.user || process.env.WORDPRESS_USER || "").trim();
	const password = (guardado?.password || process.env.WORDPRESS_APP_PASSWORD || "").replace(/\s+/g, "");
	if (!user || !password) {
		throw new Error("Faltan WORDPRESS_USER y WORDPRESS_APP_PASSWORD.");
	}
	return { url, user, password };
}

async function wp(method, ruta, body, extra) {
	const { url, user, password } = credenciales();
	const headers = {
		Authorization: `Basic ${Buffer.from(`${user}:${password}`).toString("base64")}`,
		Accept: "application/json",
		...extra,
	};
	let payload;
	if (Buffer.isBuffer(body)) {
		payload = body;
	} else if (body != null) {
		headers["Content-Type"] = "application/json";
		payload = JSON.stringify(body);
	}
	const respuesta = await fetch(`${url}${ruta}`, { method, headers, body: payload });
	const crudo = await respuesta.text();
	let datos = null;
	if (crudo) {
		try {
			datos = JSON.parse(crudo);
		} catch {
			datos = crudo;
		}
	}
	if (!respuesta.ok) {
		const mensaje = datos && typeof datos === "object" && datos.message ? datos.message : String(crudo).slice(0, 400);
		throw new Error(`WordPress respondió ${respuesta.status}: ${mensaje}`);
	}
	return datos;
}

async function completarEntrada(id, extra) {
	const cuerpo = {};
	if (extra.etiquetas?.length) {
		cuerpo.etiquetas = extra.etiquetas;
	}
	if (extra.seo_titulo) {
		cuerpo.seo_titulo = extra.seo_titulo;
	}
	if (extra.seo_descripcion) {
		cuerpo.seo_descripcion = extra.seo_descripcion;
	}
	if (extra.frase_clave) {
		cuerpo.frase_clave = extra.frase_clave;
	}
	if (extra.video_id) {
		cuerpo.video_id = extra.video_id;
	}
	if (Object.keys(cuerpo).length) {
		await wp("POST", `/wp-json/dcj/v1/entrada/${id}`, cuerpo);
	}
	if (!extra.video_id) {
		return "";
	}
	try {
		const mediaId = await ponerMiniatura(id, extra.video_id);
		return `Imagen destacada ${mediaId}`;
	} catch (error) {
		return error instanceof Error ? error.message : String(error);
	}
}

async function ponerMiniatura(entradaId, videoId) {
	const archivo = await descargarMiniatura(videoId);
	const media = await wp("POST", "/wp-json/wp/v2/media", archivo.bytes, {
		"Content-Type": archivo.mime,
		"Content-Disposition": `attachment; filename="${archivo.nombre}"`,
	});
	await wp("POST", `/wp-json/wp/v2/posts/${entradaId}`, { featured_media: media.id });
	return media.id;
}

async function descargarMiniatura(videoId) {
	if (!/^[A-Za-z0-9_-]{6,20}$/.test(videoId)) {
		throw new Error("El ID del video no es válido.");
	}
	const calidades = ["maxresdefault", "sddefault", "hqdefault", "oardefault", "mqdefault"];
	for (const calidad of calidades) {
		const respuesta = await fetch(`https://i.ytimg.com/vi/${videoId}/${calidad}.jpg`);
		if (!respuesta.ok) {
			continue;
		}
		const bytes = Buffer.from(await respuesta.arrayBuffer());
		if (bytes.length < 2000) {
			continue;
		}
		return normalizar(bytes, `${videoId}.jpg`, "image/jpeg");
	}
	throw new Error("YouTube no entregó la miniatura de ese video.");
}

async function leerLocal(ruta) {
	const absoluta = resolve(RAIZ, ruta);
	if (!existsSync(absoluta)) {
		throw new Error(`No encuentro el archivo ${absoluta}`);
	}
	const bytes = readFileSync(absoluta);
	return normalizar(bytes, basename(absoluta));
}

async function leerUrl(url) {
	const destino = new URL(url);
	if (destino.protocol !== "https:") {
		throw new Error("La URL de la imagen tiene que ser https.");
	}
	const respuesta = await fetch(destino);
	if (!respuesta.ok) {
		throw new Error(`No pude descargar la imagen (${respuesta.status}).`);
	}
	const bytes = Buffer.from(await respuesta.arrayBuffer());
	const nombre = basename(destino.pathname) || "imagen.jpg";
	return normalizar(bytes, nombre, respuesta.headers.get("content-type"));
}

function normalizar(bytes, nombre, mimeCabecera) {
	if (bytes.length > TOPE) {
		throw new Error("La imagen pasa de 12 MB.");
	}
	const extension = extname(nombre).toLowerCase();
	const mime = MIME[extension] || (mimeCabecera || "").split(";")[0].trim();
	if (!Object.values(MIME).includes(mime)) {
		throw new Error("Solo acepto jpg, png, gif o webp.");
	}
	const seguro = nombre.replace(/[^A-Za-z0-9._-]/g, "-").replace(/-+/g, "-") || "imagen.jpg";
	return { bytes, mime, nombre: seguro };
}

function aHtml(texto) {
	if (/<\/?[a-z][\s\S]*>/i.test(texto)) {
		return texto;
	}
	return texto
		.split(/\n{2,}/)
		.map((parrafo) => parrafo.trim())
		.filter(Boolean)
		.map((parrafo) => `<p>${escapar(parrafo).replace(/\n/g, "<br>")}</p>`)
		.join("\n");
}

function escapar(texto) {
	return texto
		.replaceAll("&", "&amp;")
		.replaceAll("<", "&lt;")
		.replaceAll(">", "&gt;")
		.replaceAll('"', "&quot;");
}

function resumen(entrada, mensaje) {
	return JSON.stringify({
		mensaje,
		id: entrada.id,
		estado: entrada.status,
		enlace: entrada.link,
		editar: `${(process.env.WORDPRESS_URL || "https://devconjorge.com").replace(/\/$/, "")}/wp-admin/post.php?post=${entrada.id}&action=edit`,
	}, null, 2);
}

function texto(contenido) {
	return { content: [{ type: "text", text: contenido }] };
}

function fallo(error) {
	return {
		isError: true,
		content: [{ type: "text", text: error instanceof Error ? error.message : String(error) }],
	};
}
