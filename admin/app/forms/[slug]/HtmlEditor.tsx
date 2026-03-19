"use client";

import { useRef } from "react";

type Props = {
  value: string;
  onChange: (next: string) => void;
};

export default function HtmlEditor({ value, onChange }: Props) {
  const ref = useRef<HTMLDivElement | null>(null);

  function exec(command: string): void {
    ref.current?.focus();
    document.execCommand(command);
    onChange(ref.current?.innerHTML ?? "");
  }

  return (
    <div className="htmlEditorWrap">
      <div className="htmlEditorToolbar">
        <button type="button" className="secondary" onClick={() => exec("bold")}>B</button>
        <button type="button" className="secondary" onClick={() => exec("italic")}>I</button>
        <button type="button" className="secondary" onClick={() => exec("insertUnorderedList")}>UL</button>
        <button type="button" className="secondary" onClick={() => exec("insertOrderedList")}>OL</button>
      </div>

      <div
        ref={ref}
        className="htmlEditor"
        contentEditable
        suppressContentEditableWarning
        onInput={(e) => onChange((e.currentTarget as HTMLDivElement).innerHTML)}
        dangerouslySetInnerHTML={{ __html: value }}
      />
    </div>
  );
}
