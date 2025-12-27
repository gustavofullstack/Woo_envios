// @ts-nocheck
import axios from 'axios';
import fs from 'fs';
import path from 'path';
import { glob } from 'glob';

/**
 * TriqHub Elite Documentation Agent
 * Version: 2.0.0
 * Features: Deep Context Analysis, Mermaid Diagram Generation, API Mapping
 */

const DEEPSEEK_API_URL = 'https://api.deepseek.com/chat/completions';
const DEEPSEEK_API_KEY = process.env.DEEPSEEK_API_KEY;

if (!DEEPSEEK_API_KEY) {
    console.error("❌ ERROR: DEEPSEEK_API_KEY environment variable is not set.");
    process.exit(1);
}

class DeepSeekService {
    public async generateDocumentation(context: string, name: string, promptSuffix: string): Promise<string> {
        const systemPrompt = `You are a Senior Principal Software Architect and Technical Writer at TriqHub.
Your goal is to produce "World Class", "Extremely Detailed", and "Market Standard" documentation for the project "${name}".
The user demands depth, precision, and clarity.
Output MUST be in Markdown format.
For Architecture docs, ALWAYS include at least one complex Mermaid diagram showing component relationships or data flow.
For API docs, document EVERY public hook, filter, and primary class method with parameters and return types.
Do NOT include conversational filler like "Here is the documentation...".
Start directly with the document content.`;

        try {
            const response = await axios.post(
                DEEPSEEK_API_URL,
                {
                    model: "deepseek-chat",
                    messages: [
                        { role: "system", content: systemPrompt },
                        { role: "user", content: `Context of the codebase:\n${context.substring(0, 50000)}\n\nTask: ${promptSuffix}` }
                    ],
                    temperature: 0.3,
                    max_tokens: 4000
                },
                {
                    headers: {
                        'Authorization': `Bearer ${DEEPSEEK_API_KEY}`,
                        'Content-Type': 'application/json'
                    },
                    timeout: 240000 // 4 minutes timeout for complex docs
                }
            );

            return response.data.choices[0]?.message?.content || "";
        } catch (error: any) {
            console.error(`[DeepSeek] API Error:`, error.response?.data?.error?.message || error.message);
            return "";
        }
    }
}

async function collectContext(targetPath: string): Promise<string> {
    const pattern = "**/*.{php,js,css,ts,tsx}";
    const ignore = [
        "**/node_modules/**",
        "**/vendor/**",
        "**/.git/**",
        "**/dist/**",
        "**/.next/**",
        "**/composer.phar",
        "**/assets/fonts/**"
    ];

    const files = await glob(pattern, {
        cwd: targetPath,
        ignore: ignore,
        nodir: true
    });

    let context = "";
    // Prioritize main plugin files and core logic
    const sortedFiles = files.sort((a: string, b: string) => {
        if (a.includes('main') || a.endsWith('.php') && !a.includes('/')) return -1;
        return 0;
    });

    for (const file of sortedFiles) {
        if (context.length >= 60000) break;
        try {
            const content = fs.readFileSync(path.join(targetPath, file), "utf8");
            context += `\n--- FILE: ${file} ---\n${content}\n`;
        } catch (e) { }
    }
    return context;
}

const DOC_TYPES = [
    {
        filename: "README.md",
        prompt: "Generate a premium README.md with project overview, technical stack, key features, and installation instructions."
    },
    {
        filename: "docs/ARCHITECTURE.md",
        prompt: "Generate a deep-dive ARCHITECTURE.md explaining the system design, core modules, and data flow. Include Mermaid diagrams for class hierarchy and connectivity."
    },
    {
        filename: "docs/API_REFERENCE.md",
        prompt: "Generate a technical API_REFERENCE.md documenting all public functions, WordPress actions/filters, and internal API routes with types and descriptions."
    },
    {
        filename: "docs/USER_GUIDE.md",
        prompt: "Generate a comprehensive USER_GUIDE.md for end-users, covering configuration, troubleshooting, and advanced usage scenarios."
    },
    {
        filename: "docs/CONNECTIVITY.md",
        prompt: "Generate a detailed CONNECTIVITY.md. Document external API integrations (TriqHub License API, Google Maps, WC Native), webhook structures, network timeouts, and error handling strategies. Include a Mermaid sequence diagram for a common integration flow."
    }
];

async function run() {
    const repoPath = process.cwd();
    const repoName = path.basename(repoPath);
    console.log(`🚀 Elite Docs Agent starting for: ${repoName}`);

    const service = new DeepSeekService();
    const context = await collectContext(repoPath);

    if (!context) {
        console.error("❌ No codebase context collected. Check path and filters.");
        process.exit(1);
    }

    const docsDir = path.join(repoPath, 'docs');
    if (!fs.existsSync(docsDir)) fs.mkdirSync(docsDir, { recursive: true });

    for (const doc of DOC_TYPES) {
        console.log(`📝 Generating ${doc.filename}...`);
        const content = await service.generateDocumentation(context, repoName, doc.prompt);
        if (content) {
            fs.writeFileSync(path.join(repoPath, doc.filename), content);
            console.log(`✅ ${doc.filename} created.`);
        }
    }

    console.log("\n✨ All elite documentation generated successfully!");
}

run().catch(console.error);
