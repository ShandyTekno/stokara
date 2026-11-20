<script setup>
import { computed } from 'vue';

const props = defineProps({ text: { type: String, required: true } });

function inline(text) {
    return text
        .split(/(\*\*[^*]+\*\*)/g)
        .filter(Boolean)
        .map((part) =>
            part.length > 4 && part.startsWith('**') && part.endsWith('**')
                ? { bold: true, text: part.slice(2, -2) }
                : { bold: false, text: part },
        );
}

const blocks = computed(() => {
    const out = [];
    let list = null;

    for (const raw of props.text.replace(/\r/g, '').split('\n')) {
        const line = raw.trim();
        const bullet = line.match(/^[-*•]\s+(.*)$/);

        if (bullet) {
            if (!list) {
                list = { type: 'list', items: [] };
                out.push(list);
            }
            list.items.push(inline(bullet[1]));
            continue;
        }

        list = null;
        if (!line) continue;

        out.push({ type: 'p', parts: inline(line.replace(/^#{1,6}\s+/, '')) });
    }

    return out;
});
</script>

<template>
    <div class="space-y-2 break-words">
        <template v-for="(block, i) in blocks" :key="i">
            <ul v-if="block.type === 'list'" class="list-disc space-y-1 pl-5">
                <li v-for="(item, j) in block.items" :key="j">
                    <template v-for="(part, k) in item" :key="k">
                        <strong v-if="part.bold">{{ part.text }}</strong>
                        <template v-else>{{ part.text }}</template>
                    </template>
                </li>
            </ul>
            <p v-else>
                <template v-for="(part, k) in block.parts" :key="k">
                    <strong v-if="part.bold">{{ part.text }}</strong>
                    <template v-else>{{ part.text }}</template>
                </template>
            </p>
        </template>
    </div>
</template>
