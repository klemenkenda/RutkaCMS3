"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { FormInfo } from "../../lib/api";
import type { PageTreeNode } from "./sidebar-types";

type Props = {
  forms: FormInfo[];
  pageTree: PageTreeNode[];
};

function TreeNode({ node }: { node: PageTreeNode }) {
  return (
    <li>
      <div className="treeNodeLabel">
        <span>{node.title}</span>
        <span className="treeUri">/{node.uri}</span>
      </div>
      {node.children.length > 0 && (
        <ul className="treeBranch">
          {node.children.map((child) => (
            <TreeNode key={child.id} node={child} />
          ))}
        </ul>
      )}
    </li>
  );
}

export default function SidebarNav({ forms, pageTree }: Props) {
  const pathname = usePathname();

  return (
    <aside className="sidebar card">
      <div className="sidebarBlock">
        <div className="sidebarTitle">Forms</div>
        <nav>
          <ul className="menuList">
            {forms.map((form) => {
              const href = `/forms/${form.slug}`;
              const active = pathname === href;
              return (
                <li key={form.slug}>
                  <Link href={href} className={`menuLink${active ? " active" : ""}`}>
                    <span>{form.name}</span>
                    <span className="menuMeta">{form.slug}</span>
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>
      </div>

      <div className="sidebarBlock">
        <div className="sidebarTitle">Pages Tree</div>
        {pageTree.length === 0 ? (
          <div className="sidebarHint">No pages found in `pages` table.</div>
        ) : (
          <ul className="treeRoot">
            {pageTree.map((node) => (
              <TreeNode key={node.id} node={node} />
            ))}
          </ul>
        )}
      </div>
    </aside>
  );
}
