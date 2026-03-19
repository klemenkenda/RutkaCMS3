export type FormInfo = {
  slug: string;
  name: string;
  listorder: string;
  formtype: string;
  fieldCount: number;
};

export type FormField = {
  key: string;
  name: string;
  type: string;
  comment?: string | null;
  start?: string | null;
  dir?: string | null;
  data?: string | null;
};

export type FieldOption = {
  value: string;
  label: string;
};

export type FormSchema = {
  slug: string;
  name: string;
  meta: Record<string, string>;
  fields: FormField[];
};

export type Entry = Record<string, string | number | null>;

const env =
  (globalThis as { process?: { env?: Record<string, string | undefined> } }).process
    ?.env ?? {};

const serverApi = env.API_BASE_URL_INTERNAL || env.NEXT_PUBLIC_API_BASE_URL || "http://localhost:8080";
const browserApi = env.NEXT_PUBLIC_API_BASE_URL || "http://localhost:8080";
const API = typeof window === "undefined" ? serverApi : browserApi;

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const headers: Record<string, string> = {
    ...(init?.headers as Record<string, string> | undefined),
  };

  if (!(init?.body instanceof FormData) && !headers["Content-Type"]) {
    headers["Content-Type"] = "application/json";
  }

  const response = await fetch(`${API}${path}`, {
    ...init,
    headers,
    cache: "no-store",
  });

  const body = await response.json();
  if (!response.ok) {
    const msg = body?.message || body?.error || `Request failed (${response.status})`;
    throw new Error(msg);
  }

  return body as T;
}

export async function getForms(): Promise<FormInfo[]> {
  const data = await request<{ forms: FormInfo[] }>("/api/forms");
  return data.forms;
}

export async function getFormSchema(slug: string): Promise<FormSchema> {
  return request<FormSchema>(`/api/forms/${slug}`);
}

export async function getEntries(slug: string): Promise<Entry[]> {
  const data = await request<{ data: Entry[] }>(`/api/forms/${slug}/entries`);
  return data.data;
}

export async function createEntry(slug: string, payload: Record<string, unknown>): Promise<void> {
  await request(`/api/forms/${slug}/entries`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateEntry(slug: string, id: number, payload: Record<string, unknown>): Promise<void> {
  await request(`/api/forms/${slug}/entries/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function deleteEntry(slug: string, id: number): Promise<void> {
  await request(`/api/forms/${slug}/entries/${id}`, {
    method: "DELETE",
  });
}

export async function getFieldOptions(slug: string, fieldKey: string): Promise<FieldOption[]> {
  const data = await request<{ options: FieldOption[] }>(`/api/forms/${slug}/options/${fieldKey}`);
  return data.options;
}

export async function uploadFieldFile(
  slug: string,
  fieldKey: string,
  file: File,
): Promise<{ filename: string; relativePath: string }> {
  const formData = new FormData();
  formData.append("file", file);

  return request<{ filename: string; relativePath: string }>(`/api/forms/${slug}/upload/${fieldKey}`, {
    method: "POST",
    body: formData,
  });
}
