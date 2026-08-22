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

// HTML-mall för välkomstmailet, anpassad efter sidans färger och stil.
function valkomstmailHtml(namn) {
  return `
  <div style="background:#f4f1e8; padding:2.5rem 1rem; font-family:Georgia,'Times New Roman',serif;">
    <div style="max-width:520px; margin:0 auto; background:#ffffff; border:1px solid rgba(0,0,0,0.08);">

      <div style="background:#1a1a18; padding:2rem 2rem 1.5rem; text-align:center;">
        <img src="https://gostasvarv.se/images/logo.png" alt="Kulturföreningen Gösta Johanssons Varv" style="height:64px; width:auto; margin-bottom:0.5rem;">
      </div>

      <div style="padding:2rem 2rem 1rem;">
        <h1 style="font-size:1.4rem; color:#1a1a18; margin:0 0 1.2rem; font-weight:700;">Välkommen till föreningen, ${namn}!</h1>

        <p style="font-size:0.98rem; line-height:1.7; color:#3a3830; margin:0 0 1rem;">
          Tack för din anmälan till Kulturföreningen Gösta Johanssons Varv. Vi har tagit emot dina uppgifter och du är nu registrerad som medlem.
        </p>
        <p style="font-size:0.98rem; line-height:1.7; color:#3a3830; margin:0 0 1.5rem;">
          Föreningen bevarar och utvecklar Göstas varv och det unika kulturarvet av träbåtsbyggande i Kungsviken på Orust. Som medlem är du välkommen att delta i våra arbetsdagar, aktiviteter och gemenskapen kring varvet.
        </p>

        <div style="background:#f4f1e8; border-left:3px solid #a0522d; padding:1.2rem 1.4rem; margin-bottom:1.5rem;">
          <p style="font-size:0.9rem; color:#1a1a18; margin:0 0 0.5rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">Medlemsavgift</p>
          <p style="font-size:0.95rem; color:#3a3830; margin:0 0 0.6rem;">300 kr per år, för enskild person eller familj.</p>
          <p style="font-size:0.95rem; color:#3a3830; margin:0;">
            Swish: <strong>123 268 0270</strong><br>
            Bankgiro: <strong>5427-7892</strong>
          </p>
        </div>

        <p style="font-size:0.98rem; line-height:1.7; color:#3a3830; margin:0 0 1.5rem;">
          Vi håller dig uppdaterad om kommande aktiviteter och arbetsdagar via mail och hemsida. Har du frågor är du alltid välkommen att höra av dig till oss på <a href="mailto:info@gostasvarv.se" style="color:#a0522d;">info@gostasvarv.se</a>.
        </p>

        <p style="font-size:0.98rem; line-height:1.7; color:#3a3830; margin:0 0 0.3rem;">Hälsningar,</p>
        <p style="font-size:0.98rem; line-height:1.7; color:#1a1a18; font-weight:700; margin:0;">Kulturföreningen Gösta Johanssons Varv</p>
      </div>

      <div style="background:#f4f1e8; padding:1.2rem 2rem; text-align:center; border-top:1px solid rgba(0,0,0,0.06);">
        <a href="https://gostasvarv.se" style="font-size:0.78rem; letter-spacing:0.08em; text-transform:uppercase; color:#a0522d; text-decoration:none;">gostasvarv.se</a>
        <p style="font-size:0.75rem; color:rgba(58,56,48,0.6); margin:0.5rem 0 0;">Kungsviken · Orust</p>
      </div>

    </div>
  </div>
  `;
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
      html: valkomstmailHtml(namn),
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
