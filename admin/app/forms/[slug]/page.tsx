import Link from "next/link";
import { getEntries, getFormSchema } from "../../../lib/api";
import FormManager from "./FormManager";

type Props = {
  params: { slug: string };
};

export default async function FormPage({ params }: Props) {
  const schema = await getFormSchema(params.slug);
  const entries = await getEntries(params.slug);

  return (
    <main style={{ display: "grid", gap: 14 }}>
      <div>
        <Link href="/forms" style={{ color: "#0b7a75" }}>
          {"<- Back to forms"}
        </Link>
      </div>
      <section className="card">
        <h1 style={{ marginTop: 0, marginBottom: 8 }}>{schema.name}</h1>
        <div style={{ color: "#5f6b7a" }}>slug: {schema.slug}</div>
        <div style={{ color: "#5f6b7a" }}>fields: {schema.fields.length}</div>
      </section>

      <FormManager slug={schema.slug} fields={schema.fields} initialEntries={entries} />
    </main>
  );
}
