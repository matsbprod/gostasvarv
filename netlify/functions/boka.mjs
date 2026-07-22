import { getStore } from "@netlify/blobs";

export default async (req) => {
  if (req.method !== "POST") {
    return new Response("Method not allowed", { status: 405 });
  }

  try {
    const data = await req.json();
    const { bat, datum, namn, telefon, epost } = data;

    if (!bat || !datum || !namn || !telefon || !epost) {
      return new Response(JSON.stringify({ fel: "Fyll i alla fält." }), {
        status: 400,
        headers: { "Content-Type": "application/json" },
      });
    }

    const store = getStore("bokningar");

    // Kolla om datumet redan är bokat
    const key = `${bat}__${datum}`;
    const existing = await store.get(key);
    if (existing) {
      return new Response(
        JSON.stringify({ fel: `${bat} är redan bokat ${datum}.` }),
        { status: 409, headers: { "Content-Type": "application/json" } }
      );
    }

    // Spara bokning
    await store.setJSON(key, {
      bat,
      datum,
      namn,
      telefon,
      epost,
      skapad: new Date().toISOString(),
      status: "väntar",
    });

    return new Response(JSON.stringify({ ok: true }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    });
  } catch (err) {
    return new Response(JSON.stringify({ fel: "Serverfel: " + err.message }), {
      status: 500,
      headers: { "Content-Type": "application/json" },
    });
  }
};

export const config = { path: "/api/boka" };
