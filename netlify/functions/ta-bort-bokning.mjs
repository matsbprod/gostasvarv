import { getStore } from "@netlify/blobs";

export default async (req) => {
  if (req.method !== "DELETE") {
    return new Response("Method not allowed", { status: 405 });
  }

  try {
    const { bat, datum } = await req.json();
    if (!bat || !datum) {
      return new Response(JSON.stringify({ fel: "Ange bat och datum." }), {
        status: 400,
        headers: { "Content-Type": "application/json" },
      });
    }

    const store = getStore("bokningar");
    await store.delete(`${bat}__${datum}`);

    return new Response(JSON.stringify({ ok: true }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    });
  } catch (err) {
    return new Response(JSON.stringify({ fel: err.message }), {
      status: 500,
      headers: { "Content-Type": "application/json" },
    });
  }
};

export const config = { path: "/api/ta-bort-bokning" };
