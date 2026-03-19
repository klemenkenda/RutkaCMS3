"use client";

import { useMemo, useState } from "react";
import {
  createEntry,
  deleteEntry,
  updateEntry,
  type Entry,
  type FormField,
} from "../../../lib/api";

type Props = {
  slug: string;
  fields: FormField[];
  initialEntries: Entry[];
};

export default function FormManager({ slug, fields, initialEntries }: Props) {
  const [entries, setEntries] = useState<Entry[]>(initialEntries);
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editDraft, setEditDraft] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const visibleFields = useMemo(() => fields.slice(0, 12), [fields]);

  async function onCreate() {
    setBusy(true);
    setError(null);
    try {
      await createEntry(slug, draft);
      window.location.reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not create entry.");
    } finally {
      setBusy(false);
    }
  }

  function startEdit(entry: Entry) {
    const id = Number(entry.id);
    if (!Number.isFinite(id)) {
      setError("This record has no numeric id field and cannot be edited with generic mode.");
      return;
    }

    setEditingId(id);
    const next: Record<string, string> = {};
    for (const field of fields) {
      const value = entry[field.key];
      next[field.key] = value == null ? "" : String(value);
    }
    setEditDraft(next);
  }

  async function onSaveEdit() {
    if (editingId == null) {
      return;
    }

    setBusy(true);
    setError(null);
    try {
      await updateEntry(slug, editingId, editDraft);
      window.location.reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not update entry.");
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(id: number) {
    if (!window.confirm(`Delete record #${id}?`)) {
      return;
    }

    setBusy(true);
    setError(null);
    try {
      await deleteEntry(slug, id);
      setEntries((prev) => prev.filter((entry) => Number(entry.id) !== id));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not delete entry.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div style={{ display: "grid", gap: 16 }}>
      <section className="card">
        <h2 style={{ marginTop: 0, marginBottom: 12 }}>Create new</h2>
        <div className="grid">
          {visibleFields.map((field) => (
            <div key={field.key}>
              <div className="label">{field.name}</div>
              <input
                value={draft[field.key] ?? ""}
                onChange={(e) => setDraft((prev) => ({ ...prev, [field.key]: e.target.value }))}
              />
            </div>
          ))}
        </div>
        <div style={{ marginTop: 12 }}>
          <button className="primary" disabled={busy} onClick={onCreate}>
            Create entry
          </button>
        </div>
      </section>

      {editingId != null && (
        <section className="card">
          <h2 style={{ marginTop: 0, marginBottom: 12 }}>Editing #{editingId}</h2>
          <div className="grid">
            {visibleFields.map((field) => (
              <div key={field.key}>
                <div className="label">{field.name}</div>
                <input
                  value={editDraft[field.key] ?? ""}
                  onChange={(e) =>
                    setEditDraft((prev) => ({
                      ...prev,
                      [field.key]: e.target.value,
                    }))
                  }
                />
              </div>
            ))}
          </div>
          <div style={{ marginTop: 12, display: "flex", gap: 8 }}>
            <button className="primary" disabled={busy} onClick={onSaveEdit}>
              Save changes
            </button>
            <button className="secondary" onClick={() => setEditingId(null)}>
              Cancel
            </button>
          </div>
        </section>
      )}

      <section className="card">
        <h2 style={{ marginTop: 0, marginBottom: 12 }}>Entries ({entries.length})</h2>

        {error && (
          <div style={{ marginBottom: 10, color: "#b42318" }}>
            {error}
          </div>
        )}

        <div className="tableWrap">
          <table>
            <thead>
              <tr>
                <th>id</th>
                {visibleFields.map((f) => (
                  <th key={f.key}>{f.key}</th>
                ))}
                <th>actions</th>
              </tr>
            </thead>
            <tbody>
              {entries.map((entry, index) => {
                const id = Number(entry.id);
                return (
                  <tr key={String(entry.id ?? `${slug}-${index}`)}>
                    <td>{String(entry.id ?? "-")}</td>
                    {visibleFields.map((f) => (
                      <td key={f.key}>{String(entry[f.key] ?? "")}</td>
                    ))}
                    <td>
                      <div style={{ display: "flex", gap: 8 }}>
                        <button className="secondary" onClick={() => startEdit(entry)}>
                          Edit
                        </button>
                        {Number.isFinite(id) && (
                          <button className="danger" disabled={busy} onClick={() => onDelete(id)}>
                            Delete
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
