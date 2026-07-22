import { getStore } from "@netlify/blobs";

export default async (req) => {
  const url = new URL(req.url);
  const bat = url.searchParams.get("bat");

  if (!bat) {
    return new Response(JSON.stringify({ fel: "Ange båt." }), {
      status: 400,
      headers: { "Content-Type": "application/json" },
    });
  }

  try {
    const store = getStore("bokningar");
    const { blobs } = await store.list({ prefix: `${bat}__` });

    const bokade = blobs
      .map((b) => b.key.replace(`${bat}__`, ""))
      .filter((d) => d.match(/^\d{4}-\d{2}-\d{2}$/));

    return new Response(JSON.stringify({ bokade }), {
      status: 200,
      headers: {
        "Content-Type": "application/json",
        "Access-Control-Allow-Origin": "*",
      },
    });
  } catch (err) {
    return new Response(JSON.stringify({ bokade: [] }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    });
  }
};

export const config = { path: "/api/hamta-bokningar" };
