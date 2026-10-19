"use client";

import { EditorContent, useEditor, useEditorState } from "@tiptap/react";
import Image from "@tiptap/extension-image";
import { TableKit } from "@tiptap/extension-table";
import StarterKit from "@tiptap/starter-kit";
import { Bold, Heading3, Italic, Link2, List, ListOrdered, Quote, Redo2, Undo2 } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * Rich text limited to what the API keeps after sanitising: paragraphs, h3/h4, bold,
 * italic, lists, quotes, code and links. Anything else is stripped server-side.
 * With `media` (reading lectures) tables and images in the content are kept and editable
 * too — e.g. notes imported from the old site — though there's no button to add new ones.
 */
export function RichTextEditor({
  value,
  onChange,
  placeholder,
  id,
  minHeight = 180,
  media = false,
  "aria-describedby": describedBy,
}: {
  value: string;
  onChange: (html: string) => void;
  placeholder?: string;
  id?: string;
  minHeight?: number;
  media?: boolean;
  "aria-describedby"?: string;
}) {
  const editor = useEditor({
    immediatelyRender: false,
    extensions: [
      StarterKit.configure({
        heading: { levels: [3, 4] },
        horizontalRule: false,
        strike: false,
        link: { openOnClick: false, autolink: true, protocols: ["https", "http", "mailto"] },
      }),
      ...(media ? [TableKit.configure({ table: { resizable: false } }), Image] : []),
    ],
    content: value,
    editorProps: {
      attributes: {
        id: id ?? "",
        role: "textbox",
        "aria-multiline": "true",
        ...(describedBy ? { "aria-describedby": describedBy } : {}),
        class: "prose-editor focus:outline-none px-4 py-3 text-[0.95rem] leading-relaxed text-ink",
        style: `min-height:${minHeight}px`,
        "data-placeholder": placeholder ?? "",
      },
    },
    onUpdate: ({ editor }) => onChange(editor.isEmpty ? "" : editor.getHTML()),
  });

  const state = useEditorState({
    editor,
    selector: ({ editor: e }) =>
      e
        ? {
            bold: e.isActive("bold"),
            italic: e.isActive("italic"),
            h3: e.isActive("heading", { level: 3 }),
            bullet: e.isActive("bulletList"),
            ordered: e.isActive("orderedList"),
            quote: e.isActive("blockquote"),
            link: e.isActive("link"),
            canUndo: e.can().undo(),
            canRedo: e.can().redo(),
          }
        : null,
  });

  const setLink = () => {
    if (!editor) return;
    const previous = editor.getAttributes("link").href as string | undefined;
    const url = window.prompt("Link address (https://…)", previous ?? "https://");
    if (url === null) return;
    if (url === "" || url === "https://") {
      editor.chain().focus().unsetLink().run();
      return;
    }
    editor.chain().focus().extendMarkRange("link").setLink({ href: url }).run();
  };

  const tools = [
    { label: "Bold", icon: Bold, active: state?.bold, run: () => editor?.chain().focus().toggleBold().run() },
    { label: "Italic", icon: Italic, active: state?.italic, run: () => editor?.chain().focus().toggleItalic().run() },
    {
      label: "Heading",
      icon: Heading3,
      active: state?.h3,
      run: () => editor?.chain().focus().toggleHeading({ level: 3 }).run(),
    },
    {
      label: "Bulleted list",
      icon: List,
      active: state?.bullet,
      run: () => editor?.chain().focus().toggleBulletList().run(),
    },
    {
      label: "Numbered list",
      icon: ListOrdered,
      active: state?.ordered,
      run: () => editor?.chain().focus().toggleOrderedList().run(),
    },
    { label: "Quote", icon: Quote, active: state?.quote, run: () => editor?.chain().focus().toggleBlockquote().run() },
    { label: "Link", icon: Link2, active: state?.link, run: setLink },
  ];

  return (
    <div className="overflow-hidden rounded-md border border-input bg-white focus-within:border-navy-900">
      <div
        role="toolbar"
        aria-label="Formatting"
        className="flex flex-wrap items-center gap-0.5 border-b border-line bg-paper px-1.5 py-1"
      >
        {tools.map(({ label, icon: Icon, active, run }) => (
          <button
            key={label}
            type="button"
            onClick={run}
            aria-label={label}
            aria-pressed={!!active}
            className={cn(
              "rounded-sm p-1.5 text-stone-700 hover:bg-paper-2 hover:text-navy-900",
              active && "bg-navy-100 text-navy-900",
            )}
          >
            <Icon className="size-4" />
          </button>
        ))}
        <span className="mx-1 h-5 w-px bg-line" />
        <button
          type="button"
          aria-label="Undo"
          disabled={!state?.canUndo}
          onClick={() => editor?.chain().focus().undo().run()}
          className="rounded-sm p-1.5 text-stone-700 hover:bg-paper-2 disabled:opacity-40"
        >
          <Undo2 className="size-4" />
        </button>
        <button
          type="button"
          aria-label="Redo"
          disabled={!state?.canRedo}
          onClick={() => editor?.chain().focus().redo().run()}
          className="rounded-sm p-1.5 text-stone-700 hover:bg-paper-2 disabled:opacity-40"
        >
          <Redo2 className="size-4" />
        </button>
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}
