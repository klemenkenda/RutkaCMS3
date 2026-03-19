import Link from "next/link";
import { getForms } from "../../lib/api";

export default async function FormsPage() {
  const forms = await getForms();

  return (
    <main>
      <h1 style={{ marginTop: 0 }}>Forms</h1>
      <p style={{ color: "#5f6b7a" }}>Select a legacy form to manage records.</p>

      <div className="grid">
        {forms.map((form) => (
          <Link key={form.slug} href={`/forms/${form.slug}`} className="card">
            <div style={{ fontWeight: 600 }}>{form.name}</div>
            <div style={{ color: "#5f6b7a", marginTop: 8 }}>slug: {form.slug}</div>
            <div style={{ color: "#5f6b7a" }}>fields: {form.fieldCount}</div>
            <div style={{ color: "#5f6b7a" }}>type: {form.formtype}</div>
          </Link>
        ))}
      </div>
    </main>
  );
}
