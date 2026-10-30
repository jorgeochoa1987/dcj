import { readFileSync, existsSync } from "node:fs";
import { basename, dirname, extname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
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
		description: "Crea una entrada en la bitácora. Queda en borrador salvo que el estado sea publish.",
		inputSchema: {
			titulo: z.string().min(1).describe("Título de la entrada"),
			contenido: z.string().min(1).describe("Texto o HTML. El texto plano se convierte en párrafos"),
			extracto: z.string().optional(),
			estado: z.enum(["draft", "publish", "pending"]).optional().describe("Por defecto draft"),
			slug: z.string().optional(),
		},
	},
	async ({ titulo, contenido, extracto, estado, slug }) => {
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
			return texto(resumen(e, "Entrada creada."));
		} catch (error) {
			return fallo(error);
		}
	}
);

server.registerTool(
	"actualizar_entrada",
	{
		title: "Actualizar entrada",
		description: "Actualiza título, contenido, extracto, slug o estado de una entrada existente.",
		inputSchema: {
			id: z.number().int().positive(),
			titulo: z.string().optional(),
			contenido: z.string().optional(),
			extracto: z.string().optional(),
			estado: z.enum(["draft", "publish", "pending"]).optional(),
			slug: z.string().optional(),
		},
	},
	async ({ id, titulo, contenido, extracto, estado, slug }) => {
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
			if (!Object.keys(cuerpo).length) {
				throw new Error("No hay nada que actualizar.");
			}
			const e = await wp("POST", `/wp-json/wp/v2/posts/${id}`, cuerpo);
			return texto(resumen(e, "Entrada actualizada."));
		} catch (error) {
			return fallo(error);
		}
	}
);

server.registerTool(
	"subir_imagen",
	{
		title: "Subir imagen",
		description: "Sube una imagen a Medios de WordPress desde un archivo local o una URL https. Puede dejarla como imagen destacada de una entrada o insertarla al final del texto.",
		inputSchema: {
			ruta: z.string().optional().describe("Ruta local del archivo, absoluta o relativa al proyecto"),
			url: z.string().url().optional().describe("URL https de la imagen"),
			alt: z.string().optional().describe("Texto alternativo"),
			titulo: z.string().optional(),
			entrada_id: z.number().int().positive().optional().describe("Entrada a la que se asocia"),
			destacada: z.boolean().optional().describe("Si es true y hay entrada_id, queda como imagen destacada"),
			en_el_texto: z.boolean().optional().describe("Si es true y hay entrada_id, agrega la imagen al final del contenido"),
		},
	},
	async ({ ruta, url, alt, titulo, entrada_id, destacada, en_el_texto }) => {
		try {
			if (!ruta && !url) {
				throw new Error("Indica ruta o url.");
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
			return texto(JSON.stringify(resultado, null, 2));
		} catch (error) {
			return fallo(error);
		}
	}
);

async function main() {
	const transport = new StdioServerTransport();
	await server.connect(transport);
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
	const user = (process.env.WORDPRESS_USER || "").trim();
	const password = (process.env.WORDPRESS_APP_PASSWORD || "").replace(/\s+/g, "");
	if (!user || !password) {
		throw new Error("Faltan WORDPRESS_USER y WORDPRESS_APP_PASSWORD en el archivo .env de la raíz del proyecto. Créala en WordPress: Usuarios → Perfil → Contraseñas de aplicación.");
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
