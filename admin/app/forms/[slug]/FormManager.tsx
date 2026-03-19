"use client";

import { useEffect, useMemo, useState, type ChangeEvent } from "react";
import {
  createEntry,
  deleteEntry,
  getFieldOptions,
  uploadFieldFile,
  updateEntry,
  type Entry,
  type FieldOption,
  type FormField,
} from "../../../lib/api";
import HtmlEditor from "./HtmlEditor";

type Props = {
  slug: string;
  fields: FormField[];
  initialEntries: Entry[];
};

export default function FormManager({ slug, fields, initialEntries }: Props) {
  const [entries, setEntries] = useState<Entry[]>(initialEntries);
  const [draft, setDraft] = useState<Record<string, unknown>>(() => initDraft(fields));
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editDraft, setEditDraft] = useState<Record<string, unknown>>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [uploadingField, setUploadingField] = useState<string | null>(null);
  const [fieldOptions, setFieldOptions] = useState<Record<string, FieldOption[]>>({});

  const visibleFields = useMemo(() => fields, [fields]);
  const tableFields = useMemo(() => fields.slice(0, 10), [fields]);

  useEffect(() => {
    let disposed = false;

    async function loadOptions(): Promise<void> {
      const dropdowns = fields.filter((f) => f.type === "dropdown_list");
      const next: Record<string, FieldOption[]> = {};

      for (const field of dropdowns) {
        try {
          next[field.key] = await getFieldOptions(slug, field.key);
        } catch {
          next[field.key] = [];
        }
      }

      if (!disposed) {
        setFieldOptions(next);
      }
    }

    void loadOptions();
    return () => {
      disposed = true;
    };
  }, [fields, slug]);

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
    const next: Record<string, unknown> = {};
    for (const field of fields) {
      const value = entry[field.key];
      next[field.key] = normalizeFieldValue(field.type, value);
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

  async function onUpload(
    field: FormField,
    file: File | null,
    target: "create" | "edit",
  ): Promise<void> {
    if (!file) {
      return;
    }

    setUploadingField(field.key);
    setError(null);
    try {
      const result = await uploadFieldFile(slug, field.key, file);
      if (target === "create") {
        setDraft((prev) => ({ ...prev, [field.key]: result.filename }));
      } else {
        setEditDraft((prev) => ({ ...prev, [field.key]: result.filename }));
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : "Upload failed.");
    } finally {
      setUploadingField(null);
    }
  }

  function setCreateField(key: string, value: unknown): void {
    setDraft((prev) => ({ ...prev, [key]: value }));
  }

  function setEditField(key: string, value: unknown): void {
    setEditDraft((prev) => ({ ...prev, [key]: value }));
  }

  function renderFieldControl(
    field: FormField,
    value: unknown,
    onValue: (val: unknown) => void,
    mode: "create" | "edit",
  ) {
    const type = field.type;

    if (type === "textarea") {
      return (
        <textarea
          rows={4}
          value={asString(value)}
          onChange={(e) => onValue(e.target.value)}
        />
      );
    }

    if (type === "richtextarea") {
      return <HtmlEditor value={asString(value)} onChange={onValue} />;
    }

    if (type === "checkbox") {
      return (
        <label style={{ display: "flex", alignItems: "center", gap: 8 }}>
          <input
            type="checkbox"
            checked={asBool(value)}
            onChange={(e) => onValue(e.target.checked ? 1 : 0)}
            style={{ width: "auto" }}
          />
          <span>Enabled</span>
        </label>
      );
    }

    if (type === "date") {
      return (
        <input
          type="datetime-local"
          value={toDateTimeLocal(asString(value))}
          onChange={(e) => onValue(fromDateTimeLocal(e.target.value))}
        />
      );
    }

    if (type === "dropdown_list") {
      const options = fieldOptions[field.key] ?? [];
      return (
        <select value={asString(value)} onChange={(e) => onValue(e.target.value)}>
          <option value="">-- select --</option>
          {options.map((option) => (
            <option key={`${field.key}-${option.value}`} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      );
    }

    if (type === "upload_image" || type === "filename_upload") {
      return (
        <div style={{ display: "grid", gap: 8 }}>
          <input value={asString(value)} readOnly />
          <input
            type="file"
            onChange={(e: ChangeEvent<HTMLInputElement>) =>
              void onUpload(field, e.target.files?.[0] ?? null, mode)
            }
          />
          <div style={{ color: "#5f6b7a", fontSize: "0.8rem" }}>
            {uploadingField === field.key ? "Uploading..." : "Upload file to set field value."}
          </div>
        </div>
      );
    }

    return (
      <input
        value={asString(value)}
        onChange={(e) => onValue(e.target.value)}
      />
    );
  }

  return (
    <div style={{ display: "grid", gap: 16 }}>
      <section className="card">
        <h2 style={{ marginTop: 0, marginBottom: 12 }}>Create new</h2>
        <div className="grid">
          {visibleFields.map((field) => (
            <div key={field.key}>
              <div className="label">{field.name}</div>
              {renderFieldControl(field, draft[field.key], (v) => setCreateField(field.key, v), "create")}
              {field.comment && <div className="fieldComment">{field.comment}</div>}
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
                {renderFieldControl(field, editDraft[field.key], (v) => setEditField(field.key, v), "edit")}
                {field.comment && <div className="fieldComment">{field.comment}</div>}
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
                {tableFields.map((f) => (
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
                    {tableFields.map((f) => (
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

function asString(value: unknown): string {
  if (value == null) {
    return "";
  }

  return String(value);
}

function asBool(value: unknown): boolean {
  if (typeof value === "boolean") {
    return value;
  }

  if (typeof value === "number") {
    return value !== 0;
  }

  const text = String(value ?? "").toLowerCase();
  return text === "1" || text === "true" || text === "yes" || text === "on";
}

function normalizeFieldValue(type: string, value: unknown): unknown {
  if (type === "checkbox") {
    return asBool(value) ? 1 : 0;
  }

  return value == null ? "" : String(value);
}

function toDateTimeLocal(raw: string): string {
  if (!raw) {
    return "";
  }

  const normalized = raw.replace(" ", "T");
  return normalized.length >= 16 ? normalized.slice(0, 16) : normalized;
}

function fromDateTimeLocal(raw: string): string {
  if (!raw) {
    return "";
  }

  return `${raw.replace("T", " ")}:00`;
}

function initDraft(fields: FormField[]): Record<string, unknown> {
  const draft: Record<string, unknown> = {};

  for (const field of fields) {
    if (field.type === "checkbox") {
      draft[field.key] = 0;
      continue;
    }

    if (field.type === "date" && (field.start ?? "").toUpperCase() === "NOW") {
      const now = new Date();
      const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
      draft[field.key] = fromDateTimeLocal(local);
      continue;
    }

    draft[field.key] = field.start ?? "";
  }

  return draft;
}
