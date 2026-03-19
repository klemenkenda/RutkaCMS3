import "./globals.css";
import Link from "next/link";
import type { ReactNode } from "react";
import SidebarNav from "./components/SidebarNav";
import { getSidebarData } from "./components/SidebarData";

export const metadata = {
  title: "RutkaCMS Admin",
  description: "Admin app for managing legacy RutkaCMS data",
};

export default async function RootLayout({ children }: { children: ReactNode }) {
  const { forms, pageTree } = await getSidebarData();

  return (
    <html lang="en">
      <body>
        <div className="wrapper">
          <header style={{ marginBottom: 24 }}>
            <Link href="/forms" style={{ fontWeight: 700, fontSize: "1.25rem" }}>
              RutkaCMS Admin
            </Link>
          </header>
          <div className="shell">
            <SidebarNav forms={forms} pageTree={pageTree} />
            <section className="contentArea">{children}</section>
          </div>
        </div>
      </body>
    </html>
  );
}
