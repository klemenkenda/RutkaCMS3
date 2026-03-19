import { getEntries, getForms, type Entry, type FormInfo } from "../../lib/api";
import type { PageTreeNode } from "./sidebar-types";

function asNumber(value: string | number | null | undefined): number | null {
  if (value == null || value === "") {
    return null;
  }

  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

function asString(value: string | number | null | undefined, fallback: string): string {
  if (value == null || value === "") {
    return fallback;
  }

  return String(value);
}

function buildPageTree(rows: Entry[]): PageTreeNode[] {
  const nodes = rows.map((row) => {
    const id = asNumber(row.id) ?? 0;
    const parentId = asNumber(row.pa_pid);
    return {
      id,
      parentId,
      title: asString(row.pa_title, `Page ${id}`),
      uri: asString(row.pa_uri, ""),
      children: [] as PageTreeNode[],
    };
  });

  const byId = new Map<number, PageTreeNode>();
  for (const node of nodes) {
    if (node.id > 0) {
      byId.set(node.id, node);
    }
  }

  const roots: PageTreeNode[] = [];
  for (const node of nodes) {
    if (node.parentId && byId.has(node.parentId)) {
      byId.get(node.parentId)?.children.push(node);
      continue;
    }

    roots.push(node);
  }

  const sortRecursive = (branch: PageTreeNode[]): void => {
    branch.sort((a, b) => a.title.localeCompare(b.title));
    for (const item of branch) {
      sortRecursive(item.children);
    }
  };

  sortRecursive(roots);
  return roots;
}

export async function getSidebarData(): Promise<{ forms: FormInfo[]; pageTree: PageTreeNode[] }> {
  let forms: FormInfo[] = [];
  let pageTree: PageTreeNode[] = [];

  try {
    forms = await getForms();
  } catch {
    forms = [];
  }

  try {
    const pages = await getEntries("pages");
    pageTree = buildPageTree(pages);
  } catch {
    pageTree = [];
  }

  return { forms, pageTree };
}
