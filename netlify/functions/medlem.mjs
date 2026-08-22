import { getStore } from "@netlify/blobs";

// Skickar mail via Resend (https://resend.com). Kräver env-variabeln
// RESEND_API_KEY i Netlifys site-inställningar (Site settings → Environment
// variables). Se instruktioner i chatten för hur du skaffar/konfigurerar den.
async function skickaMail({ to, subject, html, replyTo }) {
  const apiKey = process.env.RESEND_API_KEY;
  if (!apiKey) {
    console.warn("RESEND_API_KEY saknas — mail skickas inte.");
    return { skickat: false };
  }
  const res = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${apiKey}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      from: "Gösta Johanssons Varv <info@gostasvarv.se>",
      to: [to],
      reply_to: replyTo || "info@gostasvarv.se",
      subject,
      html,
    }),
  });
  if (!res.ok) {
    const text = await res.text();
    console.error("Resend-fel:", res.status, text);
    return { skickat: false };
  }
  return { skickat: true };
}

export default async (req) => {
  if (req.method !== "POST") {
    return new Response("Method not allowed", { status: 405 });
  }

  try {
    const data = await req.json();
    const namn = (data.namn || "").trim();
    const epost = (data.epost || "").trim();
    const telefon = (data.telefon || "").trim();
    const adress = (data.adress || "").trim();

    if (!namn || !epost) {
      return new Response(JSON.stringify({ fel: "Namn och e-post är obligatoriskt." }), {
        status: 400,
        headers: { "Content-Type": "application/json" },
      });
    }
    // Enkel e-postvalidering
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(epost)) {
      return new Response(JSON.stringify({ fel: "Ogiltig e-postadress." }), {
        status: 400,
        headers: { "Content-Type": "application/json" },
      });
    }

    // Spara anmälan i Blobs
    const store = getStore("medlemmar");
    const key = `${Date.now()}__${epost}`;
    await store.setJSON(key, {
      namn,
      epost,
      telefon,
      adress,
      skapad: new Date().toISOString(),
      status: "ny",
    });

    // Mejlnotifiering till styrelsen via Netlify Forms (samma mönster som bokningar)
    const siteUrl = req.headers.get("origin") || "https://gostasvarv.se";
    await fetch(`${siteUrl}/`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        "form-name": "medlem",
        namn,
        epost,
        telefon,
        adress,
      }).toString(),
    });

    // Automatiskt välkomstmejl till den nya medlemmen
    await skickaMail({
      to: epost,
      subject: "Välkommen till Gösta Johanssons Varv!",
      html: `
        <p>Hej ${namn}!</p>
        <p>Tack för din anmälan till Kulturföreningen Gösta Johanssons Varv. Vi har tagit emot dina uppgifter och du är nu registrerad.</p>
        <p>Medlemsavgiften är 300 kr per år för enskild person eller familj. Betala gärna via Swish till <strong>123 268 0270</strong> eller bankgiro <strong>5427-7892</strong>, om du inte redan gjort det.</p>
        <p>Vi håller dig uppdaterad om aktiviteter och arbetsdagar via mail.</p>
        <p>Varma hälsningar,<br>Kulturföreningen Gösta Johanssons Varv</p>
      `,
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

export const config = { path: "/api/medlem" };
