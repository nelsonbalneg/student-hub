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
    Quote,
    Undo,
    Redo,
    Link as LinkIcon,
    Image as ImageIcon,
    Type,
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
            class: 'prose prose-sm dark:prose-invert max-w-none focus:outline-none min-h-[200px] p-4',
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
    <div class="rounded-md border bg-background overflow-hidden">
        <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b bg-muted/50 p-1">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="{ 'bg-muted': editor.isActive('bold') }"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <Bold class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="{ 'bg-muted': editor.isActive('italic') }"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <Italic class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="{ 'bg-muted': editor.isActive('underline') }"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                <UnderlineIcon class="h-4 w-4" />
            </Button>
            <div class="w-px h-4 bg-border mx-1"></div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="{ 'bg-muted': editor.isActive('bulletList') }"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <List class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="{ 'bg-muted': editor.isActive('orderedList') }"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="h-4 w-4" />
            </Button>
            <div class="w-px h-4 bg-border mx-1"></div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="{ 'bg-muted': editor.isActive({ textAlign: 'left' }) }"
                @click="editor.chain().focus().setTextAlign('left').run()"
            >
                <AlignLeft class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="{ 'bg-muted': editor.isActive({ textAlign: 'center' }) }"
                @click="editor.chain().focus().setTextAlign('center').run()"
            >
                <AlignCenter class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="{ 'bg-muted': editor.isActive({ textAlign: 'right' }) }"
                @click="editor.chain().focus().setTextAlign('right').run()"
            >
                <AlignRight class="h-4 w-4" />
            </Button>
            <div class="w-px h-4 bg-border mx-1"></div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                @click="setLink"
                :class="{ 'bg-muted': editor.isActive('link') }"
            >
                <LinkIcon class="h-4 w-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="addImage">
                <ImageIcon class="h-4 w-4" />
            </Button>
            <div class="w-px h-4 bg-border mx-1"></div>
            <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="editor.chain().focus().undo().run()">
                <Undo class="h-4 w-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="editor.chain().focus().redo().run()">
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
</style>
