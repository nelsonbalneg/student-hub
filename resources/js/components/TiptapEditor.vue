<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import {
    Bold,
    Italic,
    List,
    ListOrdered,
    Undo,
    Redo,
    Link as LinkIcon,
    Image as ImageIcon,
    Underline as UnderlineIcon,
    AlignCenter,
    AlignLeft,
    AlignRight,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        Link.configure({
            openOnClick: false,
        }),
        Image,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
    ],
    editorProps: {
        attributes: {
            class: 'tiptap-editor-content prose prose-sm dark:prose-invert max-w-none focus:outline-none min-h-[200px] p-4 bg-white font-normal leading-6 text-slate-900 dark:bg-slate-900 dark:text-slate-100',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

const setLink = () => {
    const url = window.prompt('URL');
    if (url) {
        editor.value?.chain().focus().setLink({ href: url }).run();
    }
};

const addImage = () => {
    const url = window.prompt('Image URL');
    if (url) {
        editor.value?.chain().focus().setImage({ src: url }).run();
    }
};
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xs dark:border-white/10 dark:bg-slate-900"
    >
        <div
            v-if="editor"
            class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50/90 p-1.5 text-slate-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-300"
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200':
                        editor.isActive('bold'),
                }"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <Bold class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200':
                        editor.isActive('italic'),
                }"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <Italic class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200':
                        editor.isActive('underline'),
                }"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                <UnderlineIcon class="h-4 w-4" />
            </Button>
            <div class="mx-1 h-4 w-px bg-slate-200 dark:bg-white/10"></div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200':
                        editor.isActive('bulletList'),
                }"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <List class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200':
                        editor.isActive('orderedList'),
                }"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="h-4 w-4" />
            </Button>
            <div class="mx-1 h-4 w-px bg-slate-200 dark:bg-white/10"></div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200':
                        editor.isActive({ textAlign: 'left' }),
                }"
                @click="editor.chain().focus().setTextAlign('left').run()"
            >
                <AlignLeft class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200':
                        editor.isActive({ textAlign: 'center' }),
                }"
                @click="editor.chain().focus().setTextAlign('center').run()"
            >
                <AlignCenter class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200':
                        editor.isActive({ textAlign: 'right' }),
                }"
                @click="editor.chain().focus().setTextAlign('right').run()"
            >
                <AlignRight class="h-4 w-4" />
            </Button>
            <div class="mx-1 h-4 w-px bg-slate-200 dark:bg-white/10"></div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                @click="setLink"
                :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200':
                        editor.isActive('link'),
                }"
            >
                <LinkIcon class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                @click="addImage"
            >
                <ImageIcon class="h-4 w-4" />
            </Button>
            <div class="mx-1 h-4 w-px bg-slate-200 dark:bg-white/10"></div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                @click="editor.chain().focus().undo().run()"
            >
                <Undo class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                @click="editor.chain().focus().redo().run()"
            >
                <Redo class="h-4 w-4" />
            </Button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #adb5bd;
    pointer-events: none;
    height: 0;
}

.ProseMirror {
    color-scheme: light;
    font-weight: 400;
    line-height: 1.65;
    white-space: pre-wrap;
}

.dark .ProseMirror {
    color-scheme: dark;
}

.tiptap-editor-content p {
    margin: 0.5rem 0;
    font-weight: 400;
}

.tiptap-editor-content p:first-child {
    margin-top: 0;
}

.tiptap-editor-content p:last-child {
    margin-bottom: 0;
}

.tiptap-editor-content ul,
.tiptap-editor-content ol {
    margin: 0.75rem 0;
    padding-left: 1.5rem;
}

.tiptap-editor-content ul {
    list-style: disc;
}

.tiptap-editor-content ol {
    list-style: decimal;
}

.tiptap-editor-content li {
    margin: 0.25rem 0;
    padding-left: 0.125rem;
    font-weight: 400;
}

.tiptap-editor-content strong,
.tiptap-editor-content b {
    font-weight: 700;
}

.tiptap-editor-content a {
    color: #047857;
    text-decoration: underline;
    text-underline-offset: 2px;
}
</style>
